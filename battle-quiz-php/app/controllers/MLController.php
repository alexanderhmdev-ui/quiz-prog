<?php
require_once __DIR__ . '/../../services/PythonService.php';
class MLController
{
    public function health(): array { return PythonService::health(); }
    public function recommend(array $stats): array { return PythonService::recommend($stats); }
}
