<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('expenses:materialize-recurring')->monthlyOn(1, '00:05');
