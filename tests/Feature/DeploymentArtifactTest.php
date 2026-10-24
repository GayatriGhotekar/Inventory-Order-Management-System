<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentArtifactTest extends TestCase
{
    public function test_production_and_portfolio_files_are_present(): void
    {
        $files = [
            'compose.production.yaml', '.env.production.example', 'docker/nginx/Dockerfile',
            'scripts/deploy.sh', 'scripts/backup-database.sh', 'scripts/restore-database.sh',
            'docs/portfolio/ARCHITECTURE.md', 'docs/portfolio/DATABASE.md',
            'docs/portfolio/WORKFLOWS.md', 'docs/portfolio/DEPLOYMENT.md', 'docs/portfolio/PORTFOLIO.md',
        ];

        foreach ($files as $file) {
            $this->assertFileExists(base_path($file));
        }
    }

    public function test_production_defaults_are_safe(): void
    {
        $environment = file_get_contents(base_path('.env.production.example'));
        $compose = file_get_contents(base_path('compose.production.yaml'));
        $restore = file_get_contents(base_path('scripts/restore-database.sh'));

        $this->assertStringContainsString('APP_ENV=production', $environment);
        $this->assertStringContainsString('APP_DEBUG=false', $environment);
        $this->assertStringNotContainsString('.:/var/www/html', $compose);
        $this->assertStringContainsString('restart: unless-stopped', $compose);
        $this->assertStringContainsString('CONFIRM_RESTORE', $restore);
    }
}
