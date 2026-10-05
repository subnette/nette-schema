<?php declare(strict_types=1);

use Nette\Schema\Context;
use Nette\Schema\Expect;
use Nette\Schema\Helpers;
use Nette\Schema\Processor;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


test('int & min', function () {
	$schema = Expect::int()->min(10);

	Assert::same(10, (new Processor)->process($schema, 10));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 9);
	}, ['The item expects to be in range 10.., 9 given.']);
});


test('int & max', function () {
	$schema = Expect::int()->max(20);

	Assert::same(20, (new Processor)->process($schema, 20));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 21);
	}, ['The item expects to be in range ..20, 21 given.']);
});


test('int & min & max', function () {
	$schema = Expect::int()->min(10)->max(20);

	Assert::same(10, (new Processor)->process($schema, 10));
	Assert::same(20, (new Processor)->process($schema, 20));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 9);
	}, ['The item expects to be in range 10..20, 9 given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 21);
	}, ['The item expects to be in range 10..20, 21 given.']);
});


test('nullable int & min & max', function () {
	$schema = Expect::int()->min(10)->max(20)->nullable();

	Assert::same(null, (new Processor)->process($schema, null));
	Assert::same(15, (new Processor)->process($schema, 15));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 9);
	}, ['The item expects to be in range 10..20, 9 given.']);
});


test('string', function () {
	$schema = Expect::string()->min(1)->max(5);

	Assert::same('hello', (new Processor)->process($schema, 'hello'));
	Assert::same('x', (new Processor)->process($schema, 'x'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, '');
	}, ['The length of item expects to be in range 1..5, 0 bytes given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'foobar');
	}, ['The length of item expects to be in range 1..5, 6 bytes given.']);
});


test('unicode', function () {
	$schema = Expect::unicode()->min(2)->max(4);

	Assert::same('žšáé', (new Processor)->process($schema, 'žšáé'));
	Assert::same('žš', (new Processor)->process($schema, 'žš'));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'ž');
	}, ['The length of item expects to be in range 2..4, 1 characters given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, 'žšáéx');
	}, ['The length of item expects to be in range 2..4, 5 characters given.']);
});


test('array', function () {
	$schema = Expect::array()->min(1)->max(3);

	Assert::same([1], (new Processor)->process($schema, [1]));
	Assert::same([1, 2, 3], (new Processor)->process($schema, [1, 2, 3]));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The length of item expects to be in range 1..3, 0 items given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3, 4]);
	}, ['The length of item expects to be in range 1..3, 4 items given.']);
});


test('structure', function () {
	$schema = Expect::structure([])->otherItems('int')->min(1)->max(3);

	Assert::equal((object) [1], (new Processor)->process($schema, [1]));
	Assert::equal((object) [1, 2, 3], (new Processor)->process($schema, [1, 2, 3]));

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, []);
	}, ['The length of item expects to be in range 1..3, 0 items given.']);

	checkValidationErrors(function () use ($schema) {
		(new Processor)->process($schema, [1, 2, 3, 4]);
	}, ['The length of item expects to be in range 1..3, 4 items given.']);
});


test('zero upper bounds are not unconstrained', function () {
	foreach ([
		[Expect::int(), 0, 1, 'The item expects to be in range ..0, 1 given.'],
		[Expect::string(), '', 'a', 'The length of item expects to be in range ..0, 1 bytes given.'],
		[Expect::array(), [], [1], 'The length of item expects to be in range ..0, 1 items given.'],
		[Expect::structure([])->otherItems('int')->castTo('array'), [], [1], 'The length of item expects to be in range ..0, 1 items given.'],
	] as [$schema, $empty, $value, $message]) {
		$processor = new Processor;
		$schema->max(0);
		Assert::same($empty, $processor->process($schema, $empty));
		checkValidationErrors(fn() => $processor->process($schema, $value), [$message]);
		$schema->max(null);
		Assert::same($value, $processor->process($schema, $value));
	}
});


test('zero lower bound is not unconstrained', function () {
	$schema = Expect::int()->min(0);
	$processor = new Processor;
	Assert::same(0, $processor->process($schema, 0));
	checkValidationErrors(fn() => $processor->process($schema, -1), ['The item expects to be in range 0.., -1 given.']);
	$schema->min(null);
	Assert::same(-1, $processor->process($schema, -1));
});


test('unconstrained and zero ranges', function () {
	foreach ([
		['', [0.0, 0.0], 0],
		['a', [0.0, 0.0], 1],
		[[], [null, null], 0],
		[NAN, [null, null], 0],
		["\u{17D}", [1.0, 1.0], 0],
	] as [$value, $range, $errors]) {
		$context = new Context;
		Helpers::validateRange($value, $range, $context, 'unicode');
		Assert::count($errors, $context->errors);
	}
});
