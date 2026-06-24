<?php

namespace Database\Seeders;

use App\Services\EmailTemplateAutoCreateService;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplateAutoCreateService::createGlobalMasterTemplates();
        $this->command->info('Global email template master records created.');
    }
}
