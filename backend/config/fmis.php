<?php

return [
    'mock_base_url' => env('FMIS_MOCK_BASE_URL', 'http://127.0.0.1:8001/mock-api/fmis'),
    'fail_on_batch_number_contains' => env('FMIS_FAIL_MARKER', 'FAILME'),
];
