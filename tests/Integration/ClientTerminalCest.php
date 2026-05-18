<?php

declare(strict_types=1);

namespace Tests\Integration;

use Codeception\Attribute\Group;
use Comgate\SDK\ClientTerminal;
use Comgate\SDK\Comgate;
use Comgate\SDK\Entity\Codes\CurrencyCode;
use Comgate\SDK\Entity\Codes\RequestCode;
use Comgate\SDK\Entity\Money;
use Comgate\SDK\Entity\TerminalPayment;
use Comgate\SDK\Entity\TerminalRefund;
use Comgate\SDK\Entity\Response\TerminalClosingResponse;
use Comgate\SDK\Entity\Response\TerminalPaymentCancelResponse;
use Comgate\SDK\Entity\Response\TerminalPaymentCreateResponse;
use Comgate\SDK\Entity\Response\TerminalPaymentStatusResponse;
use Comgate\SDK\Entity\Response\TerminalRefundCancelResponse;
use Comgate\SDK\Entity\Response\TerminalRefundCreateResponse;
use Comgate\SDK\Entity\Response\TerminalRefundStatusResponse;
use Comgate\SDK\Entity\Response\TerminalStatusResponse;
use Comgate\SDK\Exception\ApiException;
use Tests\Support\IntegrationTester;

class ClientTerminalCest
{
	#[Group('terminal')]
	#[Group('terminal-status')]
	public function getTerminalStatusTest(IntegrationTester $I): void
	{
		$client = $this->getClient();

		$response = $client->getTerminalStatus();

		$I->assertInstanceOf(TerminalStatusResponse::class, $response);
		$I->assertNotEmpty($response->getStatus());
	}

	#[Group('terminal')]
	#[Group('terminal-payment')]
	public function createPaymentTest(IntegrationTester $I): void
	{
		$client = $this->getClient();

		$payment = $this->createTerminalPayment();
		$response = $client->createPayment($payment);

		$I->assertInstanceOf(TerminalPaymentCreateResponse::class, $response);
		$I->assertEquals(RequestCode::OK, $response->getCode());
		$I->assertNotEmpty($response->getTransId());
	}

	#[Group('terminal')]
	#[Group('terminal-payment')]
	public function getPaymentStatusTest(IntegrationTester $I): void
	{
		$client = $this->getClient();

		$payment = $this->createTerminalPayment();
		$createResponse = $client->createPayment($payment);
		$transId = $createResponse->getTransId();

		$statusResponse = $client->getPaymentStatus($transId);

		$I->assertInstanceOf(TerminalPaymentStatusResponse::class, $statusResponse);
		$I->assertEquals(RequestCode::OK, $statusResponse->getCode());
		$I->assertEquals($transId, $statusResponse->getTransId());
	}

	#[Group('terminal')]
	#[Group('terminal-payment')]
	public function cancelPaymentTest(IntegrationTester $I): void
	{
		$client = $this->getClient();

		$payment = $this->createTerminalPayment();
		$createResponse = $client->createPayment($payment);
		$transId = $createResponse->getTransId();

		$cancelResponse = $client->cancelPayment($transId);

		$I->assertInstanceOf(TerminalPaymentCancelResponse::class, $cancelResponse);
		$I->assertEquals(RequestCode::OK, $cancelResponse->getCode());
	}

	#[Group('terminal')]
	#[Group('terminal-closing')]
	public function createClosingTest(IntegrationTester $I): void
	{
		$client = $this->getClient();

		$response = $client->createClosing();

		$I->assertInstanceOf(TerminalClosingResponse::class, $response);
		$I->assertEquals(RequestCode::OK, $response->getCode());
		$I->assertIsInt($response->getBatchNumber());
		$I->assertIsArray($response->getBatchData());
	}

	#[Group('terminal')]
	#[Group('terminal-refund')]
	public function createRefundTest(IntegrationTester $I): void
	{
		$client = $this->getClient();

		$refund = $this->createTerminalRefund();
		$response = $client->createRefund($refund);

		$I->assertInstanceOf(TerminalRefundCreateResponse::class, $response);
		$I->assertEquals(RequestCode::OK, $response->getCode());
		$I->assertNotEmpty($response->getTransId());
	}

	#[Group('terminal')]
	#[Group('terminal-refund')]
	public function getRefundStatusTest(IntegrationTester $I): void
	{
		$client = $this->getClient();

		$refund = $this->createTerminalRefund();
		$createResponse = $client->createRefund($refund);
		$transId = $createResponse->getTransId();

		$statusResponse = $client->getRefundStatus($transId);

		$I->assertInstanceOf(TerminalRefundStatusResponse::class, $statusResponse);
		$I->assertEquals(RequestCode::OK, $statusResponse->getCode());
		$I->assertEquals($transId, $statusResponse->getTransId());
	}

	#[Group('terminal')]
	#[Group('terminal-refund')]
	public function cancelRefundTest(IntegrationTester $I): void
	{
		$client = $this->getClient();

		$refund = $this->createTerminalRefund();
		$createResponse = $client->createRefund($refund);
		$transId = $createResponse->getTransId();

		$cancelResponse = $client->cancelRefund($transId);

		$I->assertInstanceOf(TerminalRefundCancelResponse::class, $cancelResponse);
		$I->assertEquals(RequestCode::OK, $cancelResponse->getCode());
	}

	#[Group('terminal')]
	#[Group('terminal-payment')]
	public function createPaymentFailTest(IntegrationTester $I): void
	{
		$client = $this->getClient();
		$client->getTransport()->getConfig()->setMerchant('invalid_merchant');

		$I->expectThrowable(ApiException::class, function () use ($client) {
			$client->createPayment($this->createTerminalPayment());
		});
	}

	private function createTerminalPayment(): TerminalPayment
	{
		return (new TerminalPayment())
			->setPrice(Money::ofInt(100))
			->setCurr(CurrencyCode::CZK)
			->setRefId('terminal-test-' . uniqid());
	}

	private function createTerminalRefund(): TerminalRefund
	{
		return (new TerminalRefund())
			->setPrice(Money::ofInt(50))
			->setCurr(CurrencyCode::CZK)
			->setRefId('terminal-refund-' . uniqid());
	}

	private function getClient(): ClientTerminal
	{
		return Comgate::defaults()
			->setMerchant($_ENV['API_MERCHANT'])
			->setSecret($_ENV['API_SECRET'])
			->setUrl($_ENV['API_URL_REST'])
			->createTerminalClient();
	}
}
