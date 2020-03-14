<?php
// A test class should implement ITest
// Typically it has a 1:1 relation with a class you want to unit test

class testTempie implements Tigrez\IExpect\ITest{

	protected function testPermanentFilter(Tigrez\IExpect\Assertion $I){

		/* I expect that....................
			If I inject a permanent filter into my template
		*/
		$tempie = new Tigrez\Tempie\Tempie("{{city1}} or {{city2}} or {{city3}}");
		$tempie->setPermanentFilters(['ucfirst']);
		$tempie->load(
			[
				'city1' => 'new YOrk',
				'city2' => 'DETROIT',
				'city3' => 'mobIle',
			]
		);
		$result = $tempie->render();

		/* .. the filter will be used on all values even if it is not specified */
		$I->expect($result)->equals('New york or Detroit or Mobile');

		/* I expect that....................
			If I inject a permanent filter into my template _and_ use a filter on the variable
		*/
		$tempie = new Tigrez\Tempie\Tempie("{{city|question}}");
		$tempie->setPermanentFilters(['exclamation']);
		$tempie->load(
			[
				'city' => 'Detroit',
			]
		);
		$result = $tempie->render();

		/* .. the permanent filter will be used _before_ the specific filter (so !? and not ?!) */
		$I->expect($result)->equals('Detroit!?');

	}

	protected function testFilter(Tigrez\IExpect\Assertion $I){

		/* I expect that....................
			If I render a template containing a variable with a filter expression which reverses a string
		*/
		$tempie = new Tigrez\Tempie\Tempie("{{name|reverse}}");
		$tempie->load(['name' => 'Stiltskin']);
		$result = $tempie->render();

		/* .. the rendered text will contain the value as a reversed string */
		$I->expect($result)->equals('nikstlitS');

		/* I expect that....................
			If I render a template containing a variable with two filter expressions
		*/
		$tempie = new Tigrez\Tempie\Tempie("{{name|reverse|ucfirst}}");
		$tempie->load(['name' => 'Stiltskin']);
		$result = $tempie->render();

		/* .. the rendered text will contain the value processed by both filters */
		$I->expect($result)->equals('Nikstlits');

		/* I expect that....................
			If I render a template containing a variable with two filter expressions
			but the first filter class doesn't exist
		*/
		ErrorLogger::empty();

		$tempie = new Tigrez\Tempie\Tempie("{{name|error|ucfirst}}");
		$tempie->load(['name' => 'goforit']);
		$tempie->config(['logCallback' => function($text){
			ErrorLogger::log($text);
		}]);

		$result = $tempie->render();

		/* .. the rendered text will contain the value processed by the second filter */
		$I->expect($result)->equals('Goforit');

		/* .. and a message will be generated telling the filter could not be found */
		$I->expect(ErrorLogger::$logText)->contains('error filter assumes existence of class errorFilter which cannot be found');

		/* I expect that....................
		   If I render a template containing several variables with the same filter
		*/
		$tempie = new Tigrez\Tempie\Tempie("{{name1|question}}{{name2|question}}{{name3|question}}");
		$tempie->load(['name1' => 'Jo', 'name2'=>'Ho', 'name3'=>'Mo']);
		$result = $tempie->render();

		/* .. the filter class will be instantiated only once */
		$I->expect(questionFilter::$createCount)->equals(1);
		/* .. and all values have been filtered */
		$I->expect($result)->equals('Jo?Ho?Mo?');

		/* I expect that....................
			If I render a template containing a variable with a filter expression
			but the filter class doesn't implement the Filter interface
		*/
		ErrorLogger::empty();

		$tempie = new Tigrez\Tempie\Tempie("Hello {{name|wrong}}!");
		$tempie->load(['name' => 'Bill']);
		$tempie->config(['logCallback' => function($text){
			ErrorLogger::log($text);
		}]);

		$result = $tempie->render();

		/* .. a message will be generated telling the filter does not implement interface */
		$x = ErrorLogger::$logText;
		$I->expect(ErrorLogger::$logText)->contains('The wrongFilter class is not an implementation of Tigrez\Tempie\Filter');

		/* .. but the value will still have been resolved (only without the filter) */
		$I->expect($result)->equals('Hello Bill!');

	}

	protected function testComment(Tigrez\IExpect\Assertion $I){

		/* I expect that....................
			If I render a template containing a remark
		*/
		$tempie = new Tigrez\Tempie\Tempie("[*]Some text[/*]");
		$result = $tempie->render();

		/* .. the remark will not end up in the rendered text */
		$I->expect($result)->equals('');

		/* I expect that....................
			If I render a template containing a remark
		*/
		$tempie = new Tigrez\Tempie\Tempie("[*]Some text[/*]Tempie!");
		$result = $tempie->render();

		/* .. only the remark will be excluded, not the rest */
		$I->expect($result)->equals('Tempie!');

	}

	protected function testForeach(Tigrez\IExpect\Assertion $I){
		/* I expect that....................
			If I render a template containing a foreach expression
		*/
		$tempie = new Tigrez\Tempie\Tempie("[foreach] cities as city -> {{city}} [/foreach]");
		$tempie->load(['cities' => ['London', 'Berlin', 'Madrid', 'Paris']]);
		$result = $tempie->render();

		/* .. each array element to end up in the rendered string  */
		$I->expect($result)->contains('London');
		$I->expect($result)->contains('Berlin');
		$I->expect($result)->contains('Madrid');
		$I->expect($result)->contains('Paris');

		/* I expect that....................
			If I render a template containing a foreach expression without closing tag
		*/
		ErrorLogger::empty();
		$tempie = new Tigrez\Tempie\Tempie("[foreach] cities as city -> {{city}}");
		$tempie->config(['logCallback' => function($text){
			ErrorLogger::log($text);
		}]);
		$tempie->load(['cities' => ['London', 'Berlin', 'Madrid', 'Paris']]);
		$result = $tempie->render();

		/* .. there will be a error msg about a missing foreach tag */
		$x = ErrorLogger::$logText;
		$I->expect(ErrorLogger::$logText)->contains('[foreach] without [/foreach]');

		/* todo more error situations */
	}

	protected function testIf(Tigrez\IExpect\Assertion $I){

		/* I expect that....................
			If I render a template containing an if with a true expression
		*/
		$tempie = new Tigrez\Tempie\Tempie("Your grade is {{grade}}, [if]passed -> You passed[/if]");
		$tempie->load(['grade' => 9, 'passed' => true]);
		$result = $tempie->render();

		/* .. the conditional part will end up in the rendered string  */
		$I->expect($result)->contains('You passed');

		/* I expect that....................
			If I render a template containing an if with a false expression
		*/
		$tempie = new Tigrez\Tempie\Tempie("Your grade is {grade}, [if]passed -> You passed[/if]");
		$tempie->load(['grade' => 4, 'passed' => false]);
		$result = $tempie->render();

		/* .. the conditional part will not end up in the rendered string  */
		$I->expect($result)->not()->contains('You passed');

	}

	protected function testVariableResolve(Tigrez\IExpect\Assertion $I){

		/* I expect that....................
			If I render a template file with data
		*/
		$data = [
			'name' => 'Quigle',
			'day' => 'friday',
			'city' => 'Oak Hill'
		];

		$tempie = new Tigrez\Tempie\Tempie('views/test01.tpi');
		$tempie->load($data);
		$result = $tempie->render();

		/* .. the variables in the templatefile will be resolved  */
		$I->expect($result)->contains('I am Quigle and on friday I live in Oak Hill');

		/* I expect that....................
			If I render a template offered as string with data
		*/
		$tempie = new Tigrez\Tempie\Tempie('I am {{name}} and on {{day}} I live in {{city}}');
		$tempie->load($data);
		$result = $tempie->render();

		/* .. the variables in the templatestring will be resolved  */
		$I->expect($result)->contains('I am Quigle and on friday I live in Oak Hill');

		/* I expect that....................
			If I render a template string  with array data
		*/
		$template = 'I am {{person.name}} and on {{person.day}} I live in {{person.city}} '.
					'A deeply nested array, but what keeps the doctor away? '.
					'{{deep.nested.stuff.here}}';
		$data = [
			'person' => [
				'name' => 'Quigle',
				'day' => 'friday',
				'city' => 'Oak Hill'
			],
			'deep' => ['nested' => ['stuff' => ['here' => 'one apple each day']]]
		];

		$tempie = new Tigrez\Tempie\Tempie($template);
		$tempie->load($data);
		$result = $tempie->render();

		/* ..the array data will be resolved in correct way */
		$I->expect($result)->contains('I am Quigle and on friday I live in Oak Hill');
		$I->expect($result)->contains('A deeply nested array, but what keeps the doctor away? one apple each day');

}

	// The only method ITest forces you to implement is run()
	public function run(Tigrez\IExpect\Assertion $I){

		$this->testVariableResolve($I);
		$this->testIf($I);
		$this->testForeach($I);
		$this->testComment($I);
		$this->testFilter($I);
		$this->testPermanentFilter($I);

	}
}

class reverseFilter implements Tigrez\Tempie\Filter
{
	public function filter($value, array $params){
		return strrev($value);
	}
}
class ucfirstFilter implements Tigrez\Tempie\Filter
{
	public function filter($value, array $params){
		return ucfirst(strtolower($value));
	}
}
class exclamationFilter implements \Tigrez\Tempie\Filter
{
	public function filter($value, array $params){
		return $value.'!';
	}
}

class questionFilter implements Tigrez\Tempie\Filter
{
	public static $createCount = 0;

	public function filter($value, array $params){
		return $value.'?';
	}

	public function __construct(){
		self::$createCount++;
	}
}

// Filter class not based on Tigrez\Tempie\Filter
class wrongFilter
{
	public function filter($value, array $params){
		return '*****'.$value;
	}
}

/* Simple class which makes it possible for tests to refer to logged text */
class ErrorLogger
{
	public static $logText;

	public static function empty(){
		self::$logText = '';
	}

	public static function log($text){
		self::$logText.=$text;
	}
}