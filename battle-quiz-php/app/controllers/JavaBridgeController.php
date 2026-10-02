<?php
require_once __DIR__ . '/../../services/JavaService.php';
class JavaBridgeController
{
    public function health(): array { return JavaService::health(); }
}
