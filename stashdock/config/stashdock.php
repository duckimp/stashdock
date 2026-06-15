<?php

return [
    'parent_dir' => env('STASHDOCK_PARENT_DIR', dirname(base_path())),
    'excluded_folders' => array_filter(array_map('trim',
        explode(',', env('STASHDOCK_EXCLUDED', 'git-dashboard-tools,stashdock'))
    )),
];
