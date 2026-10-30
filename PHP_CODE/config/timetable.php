<?php

return [
    // Search budget for the Timetable V2 generator. The defaults keep a web request short;
    // schools with many sections, labs and part-time teachers may need more.
    'max_search_nodes' => (int) env('TIMETABLE_MAX_SEARCH_NODES', 20000),
    'max_seconds'      => (int) env('TIMETABLE_MAX_SECONDS', 5),
];
