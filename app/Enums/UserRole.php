<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Operator = 'operator';
}
