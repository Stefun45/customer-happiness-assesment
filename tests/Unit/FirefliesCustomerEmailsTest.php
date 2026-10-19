<?php

namespace Tests\Unit;

use App\Services\FirefliesService;
use Tests\TestCase;

class FirefliesCustomerEmailsTest extends TestCase
{
    public function test_internal_and_blank_emails_are_ignored(): void
    {
        config(['integrations.internal_email_domains' => ['thedespatchcompany.com']]);

        $emails = (new FirefliesService(null))->customerEmails([
            ['email' => 'Will.Slack@TheDespatchCompany.com'],
            ['email' => null],
            ['email' => ' stuart@theartisanfoodcompany.com '],
            ['displayName' => 'No email'],
        ]);

        $this->assertSame(['stuart@theartisanfoodcompany.com'], $emails);
    }
}
