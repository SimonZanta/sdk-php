<?php

declare(strict_types=1);

namespace Tests\Integration;

use Codeception\Attribute\DataProvider;
use Codeception\Attribute\Group;
use Codeception\Example;
use Comgate\SDK\ClientTerminal;
use Comgate\SDK\Entity\Money;
use DateTime;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\IntegrationTester;

class TerminalMethodSyncCest
{
	private array $skippedMethods = ['getTransport', 'setTransport'];

	/**
	 * Ověřuje, že parametry každé lokální metody ClientTerminal odpovídají parametrům v remote OpenAPI spec.
	 */
	#[Group('terminal-method-sync')]
	#[DataProvider('getLocalMethodNames')]
	public function methodsSyncTest(IntegrationTester $I, Example $example): void
	{
		$properties = $this->getMethodsProperties();
		$methodProperties = $properties[$example['name']] ?? null;
		$I->assertNotEmpty($methodProperties, "Properties for method {$example['name']} not found. Add them to getMethodsProperties() method.");

		$remoteMethods = $this->getRemoteMethods();
		$I->assertNotEmpty($remoteMethods, "Remote methods not found. Check if http://payments.comgate.cz/openapi.yml is available.");

		$currentRemoteMethodParams = [];
		foreach ($remoteMethods as $method) {
			if ($method['url'] === $methodProperties['url']) {
				$currentRemoteMethodParams = $method['params'];
				break;
			}
		}

		$I->assertNotEmpty($currentRemoteMethodParams, "No remote params found for method {$example['name']}. Is it intentional?");

		$namespace = $methodProperties['namespace'] ?? "Comgate\\SDK\\Entity\\Request\\" . $methodProperties['class'];

		$localMethodParams = $this->getLocalMethodParams($namespace, $methodProperties['args'] ?? null);
		$diffRemote = array_diff($currentRemoteMethodParams, $localMethodParams);
		$diffLocal = array_diff($currentRemoteMethodParams, $localMethodParams);
		$I->assertEmpty($diffRemote, "Local implementation is missing some parameters.");
		$I->assertEmpty($diffLocal, "Local implementation has more parameters than remote.");
	}

	private function getLocalMethodNames(): array
	{
		$mirror = new ReflectionClass(ClientTerminal::class);

		$methodNamesRaw = array_map(function (ReflectionMethod $item) {
			return ['name' => $item->getName()];
		}, $mirror->getMethods(ReflectionMethod::IS_PUBLIC));

		return array_filter($methodNamesRaw, function ($item) {
			return $item['name'] !== '__construct' && !in_array($item['name'], $this->skippedMethods, true);
		});
	}

	private function getRemoteMethods(): array
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://payments.comgate.cz/openapi.yml');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$result = curl_exec($ch);
		if (PHP_VERSION_ID < 80500) {
			curl_close($ch);
		}

		$parsed = yaml_parse($result);
		$availableMethods = array_keys($parsed['paths']);

		return array_map(function ($method) use ($parsed) {
			$paramsParsed = $parsed['paths'][$method]['post']['requestBody']['content']['application/x-www-form-urlencoded']['schema']['properties'] ?? [];
			$params = array_keys($paramsParsed);
			$filteredParams = array_filter($params, function ($item) {
				return $item !== 'merchant' && $item !== 'secret';
			});
			return [
				'url' => $method,
				'params' => $filteredParams,
			];
		}, $availableMethods);
	}

	private function getLocalMethodParams(string $namespace, ?array $args): array
	{
		$mirror = new ReflectionClass($namespace);

		$constructor = $mirror->getConstructor();
		if ($constructor && ($constructor->getNumberOfRequiredParameters() > 0)) {
			$instance = $mirror->newInstanceArgs($args);
		} else {
			$instance = $mirror->newInstance();
		}

		$properties = [];
		foreach ($mirror->getProperties() as $prop) {
			$prop->setAccessible(true);
			if ($prop->getName() === 'params') {
				$properties = array_merge($properties, array_keys($prop->getValue($instance)));
			} else {
				$properties[] = $prop->getName();
			}
		}
		return $properties;
	}

	/**
	 * Mapování metod ClientTerminal na jejich URL a request třídy.
	 * Po přidání nové metody je třeba ji zaregistrovat zde.
	 */
	private function getMethodsProperties(): array
	{
		return [
			'createPayment' => [
				'url' => '/v1.0/terminalPayment',
				'class' => 'TerminalPaymentCreateRequest',
				'namespace' => 'Comgate\\SDK\\Entity\\TerminalPayment',
			],
			'getPaymentStatus' => [
				'url' => '/v1.0/terminalPayment/transId/{transId}',
				'class' => 'TerminalPaymentStatusRequest',
				'args' => ['AAAA-BBBB-CCCC'],
			],
			'cancelPayment' => [
				'url' => '/v1.0/terminalPayment/transId/{transId}',
				'class' => 'TerminalPaymentCancelRequest',
				'args' => ['AAAA-BBBB-CCCC'],
			],
			'createClosing' => [
				'url' => '/v1.0/terminalClosing',
				'class' => 'TerminalClosingRequest',
			],
			'createRefund' => [
				'url' => '/v1.0/terminalRefund',
				'class' => 'TerminalRefundCreateRequest',
				'namespace' => 'Comgate\\SDK\\Entity\\TerminalRefund',
			],
			'getRefundStatus' => [
				'url' => '/v1.0/terminalRefund/transId/{transId}',
				'class' => 'TerminalRefundStatusRequest',
				'args' => ['AAAA-BBBB-CCCC'],
			],
			'cancelRefund' => [
				'url' => '/v1.0/terminalRefund/transId/{transId}',
				'class' => 'TerminalRefundCancelRequest',
				'args' => ['AAAA-BBBB-CCCC'],
			],
			'getTerminalStatus' => [
				'url' => '/v1.0/terminal',
				'class' => 'TerminalStatusRequest',
			],
		];
	}
}
