<?php

namespace Tests\Feature;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ResendMailTransportTest extends TestCase
{
    public function test_configured_driver_constructs_and_sdk_sends_through_https_with_mock_transport(): void
    {
        config(['services.resend.key' => 're_dummy_test_key']);
        $this->assertInstanceOf(ResendTransport::class, Mail::mailer('resend')->getSymfonyTransport());

        // Exercise Laravel + the installed SDK without sending email or contacting a provider.
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{"id":"test-message-id"}')]));
        $stack->push(Middleware::history($history));
        $sdk = new Client(new HttpTransporter(
            new HttpClient(['handler' => $stack]), BaseUri::from('api.resend.com'),
            Headers::withAuthorization(ApiKey::from('re_dummy_test_key')),
        ));
        $transport = new ResendTransport($sdk);
        $transport->send((new Email)->from('sender@example.com')->to('student@example.com')
            ->subject('SIGNIA transport check')->text('Test only'));

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('https://api.resend.com/emails', (string) $request->getUri());
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('Bearer re_dummy_test_key', $request->getHeaderLine('Authorization'));
        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('SIGNIA transport check', $payload['subject']);
        $this->assertSame(['student@example.com'], $payload['to']);
    }
}
