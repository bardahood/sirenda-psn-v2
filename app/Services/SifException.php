<?php

namespace App\Services;

use RuntimeException;

/** Galat dari API SIF; code = status HTTP (0 bila belum dikonfigurasi/tidak terhubung). */
class SifException extends RuntimeException {}
