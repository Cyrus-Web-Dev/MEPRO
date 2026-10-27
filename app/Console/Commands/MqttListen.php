<?php

namespace App\Console\Commands;

use App\Mqtt\MessageHandler;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Throwable;

class MqttListen extends Command
{
    protected $signature = 'mqtt:listen';

    protected $description = 'Subscribe ke broker MQTT dan proses data mesin (jalankan lewat Supervisor)';

    private bool $stop = false;

    private ?MqttClient $mqtt = null;

    public function handle(MessageHandler $handler): int
    {
        $cfg = config('mepro.mqtt');

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $onStop = function () {
                $this->stop = true;
                $this->mqtt?->interrupt();
            };
            pcntl_signal(SIGTERM, $onStop);
            pcntl_signal(SIGINT, $onStop);
        }

        while (! $this->stop) {
            try {
                $this->mqtt = new MqttClient($cfg['host'], $cfg['port'], $cfg['client_id']);
                $settings = (new ConnectionSettings())
                    ->setUsername($cfg['username'] ?: null)
                    ->setPassword($cfg['password'] ?: null)
                    ->setKeepAliveInterval(30)
                    ->setConnectTimeout(10)
                    ->setUseTls((bool) $cfg['tls']);

                $this->mqtt->connect($settings, true);
                $filter = rtrim($cfg['topic_prefix'], '/') . '/+/+';
                $this->info("Terhubung ke {$cfg['host']}:{$cfg['port']}, subscribe {$filter}");

                $this->mqtt->subscribe($filter, function (string $topic, string $message) use ($handler) {
                    $this->process($handler, $topic, $message);
                }, MqttClient::QOS_AT_LEAST_ONCE);

                $this->mqtt->loop(true);
                $this->mqtt->disconnect();
            } catch (Throwable $e) {
                Log::error('MQTT listener error: ' . $e->getMessage());
                $this->error($e->getMessage());
            }

            if (! $this->stop) {
                $this->warn('Koneksi terputus, mencoba lagi dalam 5 detik...');
                sleep(5);
            }
        }

        return self::SUCCESS;
    }

    private function process(MessageHandler $handler, string $topic, string $message): void
    {
        try {
            $handler->handle($topic, $message);
        } catch (QueryException $e) {
            // Koneksi DB bisa putus pada proses yang berjalan lama: sambung ulang lalu coba sekali lagi
            Log::warning('MQTT: DB error, reconnect. ' . $e->getMessage());
            try {
                DB::reconnect();
                $handler->handle($topic, $message);
            } catch (Throwable $e2) {
                Log::error('MQTT: gagal memproses pesan setelah reconnect: ' . $e2->getMessage(), ['topic' => $topic]);
            }
        } catch (Throwable $e) {
            Log::error('MQTT: gagal memproses pesan: ' . $e->getMessage(), ['topic' => $topic]);
        }
    }
}
