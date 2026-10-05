<?php declare(strict_types=1);

use Nette\Schema\Context;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('simple tranformation', function () {
	$schema = Expect::string()->transform(fn($s) => strrev($s));

	Assert::same('olleh', (new Processor)->process($schema, 'hello'));
});


test('validation via transform', function () {
	$schema = Expect::int()
		->transform(function ($val, Context $context) {
			if ($val > 3) {
				$context->addError('Bigger than 3', 'my');
			}
			return $val;
		})
		->transform(function ($s, Context $context) {
			if ($s > 5) {
				$context->addError('Bigger than 5', 'my');
			}
			return $s;
		});

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 10);
	}, ['Bigger than 3']);

	Assert::same(2, (new Processor)->process($schema, 2));
});


test('multiple tranformation/assertions', function () {
	$schema = Expect::string()
		->assert('ctype_lower')
		->transform(fn($s) => strtoupper($s))
		->assert('ctype_upper')
		->transform(fn($s) => strrev($s));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'ABC');
	}, ["Failed assertion ctype_lower() for item with value 'ABC'."]);

	Assert::same('CBA', (new Processor)->process($schema, 'abc'));
});


test('valid siblings transform despite previous errors', function () {
	$context = new Context;
	$existing = $context->addError('Existing error', 'existing');
	$calls = [];
	$schema = Expect::structure([
		'bad' => Expect::int()->transform(function ($value) use (&$calls) {
			$calls[] = 'bad';
			return $value;
		}),
		'good' => Expect::int()->transform(function ($value, Context $context) use (&$calls) {
			$calls[] = [$context->path, $value * 2];
			return $value * 2;
		}),
		'nested' => Expect::structure(['value' => Expect::int()])
			->transform(function ($value, Context $context) use (&$calls) {
				$calls[] = [$context->path, $value->value];
				return $value;
			}),
	])->transform(function ($value) use (&$calls) {
		$calls[] = 'parent';
		return $value;
	});

	Assert::null($schema->complete(['bad' => 'invalid', 'good' => 3, 'nested' => ['value' => 4]], $context));
	Assert::same([[['good'], 6], [['nested'], 4]], $calls);
	Assert::count(2, $context->errors);
	Assert::same($existing, $context->errors[0]);
	Assert::same(['bad'], $context->errors[1]->path);
	Assert::same([], $context->path);
});


test('transform chains stop on increased or decreased error counts', function () {
	foreach ([false, true] as $removeErrors) {
		foreach ([[Expect::int(), 1], [Expect::structure([])->castTo('array'), []]] as [$schema, $input]) {
			$context = new Context;
			$context->addError('Existing error', 'existing');
			$calls = [];
			$schema
				->transform(function ($value, Context $context) use (&$calls, $removeErrors) {
					$calls[] = 'first';
					if ($removeErrors) {
						$context->errors = [];
					} else {
						$context->addError('Stop', 'stop');
					}
					return $value;
				})
				->transform(function ($value) use (&$calls) {
					$calls[] = 'second';
					return $value;
				});

			Assert::null($schema->complete($input, $context));
			Assert::same(['first'], $calls);
			Assert::count($removeErrors ? 0 : 2, $context->errors);
		}
	}
});


test('transform chains continue when errors are replaced without changing their count', function () {
	foreach ([[Expect::int(), 1], [Expect::structure([])->castTo('array'), []]] as [$schema, $input]) {
		$context = new Context;
		$context->addError('Existing error', 'existing');
		$calls = [];
		$schema
			->transform(function ($value, Context $context) use (&$calls) {
				$calls[] = 'first';
				$context->errors = [];
				$context->addError('Replacement', 'replacement');
				return 2;
			})
			->transform(function ($value) use (&$calls) {
				$calls[] = ['second', $value];
				return $value + 1;
			});

		Assert::same(3, $schema->complete($input, $context));
		Assert::same(['first', ['second', 2]], $calls);
		Assert::count(1, $context->errors);
		Assert::same('replacement', $context->errors[0]->code);
	}
});
