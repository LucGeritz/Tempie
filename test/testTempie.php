<?php
// A test class should implement ITest
// Typically it has a 1:1 relation with a class you want to unit test

class testTempie implements Tigrez\IExpect\ITest{

	protected function testVariableResolve(Tigrez\IExpect\Assertion $I){
		
		/* I expect that....................
			If I render a template file with data
		*/
		$data = [
			'name' => 'Quigle',
			'day' => 'friday',
			'city' => 'Oak Hill'
		];
		
		$tempie = new Tigrez\Tempie('views/test01.tpi');
		$tempie->load($data);
		$result = $tempie->render();
	
		/* .. the variables in the templatefile will be resolved  */
		$I->expect($result)->contains('I am Quigle and on friday I live in Oak Hill');
		
		/* I expect that....................
			If I render a template offered as string with data
		*/
		$tempie = new Tigrez\Tempie('I am {{name}} and on {{day}} I live in {{city}}');
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
		
		
		$tempie = new Tigrez\Tempie($template);
		$tempie->load($data);
		$result = $tempie->render();
		
		/* ..the array data will be resolved in correct way */
		$I->expect($result)->contains('I am Quigle and on friday I live in Oak Hill');
		$I->expect($result)->contains('A deeply nested array, but what keeps the doctor away? one apple each day');
		
}		
	
	
	// The only method ITest forces you to implement is run()	
	public function run(Tigrez\IExpect\Assertion $I){
		
		$this->testVariableResolve($I);
		//$this->testIf($I);
		//$this->testForeach($I);
		//$this->testComment($I);
	}	
}