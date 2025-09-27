<?php

namespace Boiler\Services;

class Core {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    public function init() {
        // Instantiate and initialize other classes
        new CPT();
        new Roles();
        new Admin();
        new Forms();
        new REST();
        new Database();
        new Timeline();
    }
}