<?php
namespace Tigrez;

class Tempie
{
	private $content;
	private $varTags;
	private $ifTags;
	private $ifSeparator;
	private $foreachTags;
	private $foreachSeparator;
	private $remarkTags;

	private $data = [];
	private $file;
	private $logCallback;

	private function logError($errorText)
	{
		if ($this->logCallback) {
			call_user_func($this->logCallback, "** tmpl err ({$this->file}): $errorText");
		}
	}

	private function getValueFromArray($keys, $data)
	{
		if (!is_array($keys)) {
			$keys = explode('.', $keys);
		}

		foreach ($keys as $key) {
			if (array_key_exists($key, $data)) {
				$data = $data[$key];
				if (is_a($data, 'Tigrez\Tempie')) {
					$data = $data->render();
				}
			} else {
				return null;
			}
		}
		return $data;
	}

	private function findInnerMostTag($text, $tags)
	{
		// gather positions of all tags
		$positions = [];
		for ($tag = 0; $tag < 2; $tag++) {
			$start = 0;
			while (($pos = stripos($text, $tags[$tag], $start)) !== false) {
				$positions[$pos] = $tag;
				if ($tag == 1) break;
				$start = $pos + strlen($tags[$tag]);
			}
		}
		// find innermost part
		if ($positions) {
			ksort($positions);
			foreach ($positions as $key => $value) {
				if ($value == 1) {
					$to = $key;
					break;
				} else {
					$from = $key;
				}
			}
			if (!isset($from)) {
				$this->logError(sprintf('%s without %s', $tags[1], $tags[0]));
				return [];
			}
			if (!isset($to)) {
				$this->logError(sprintf('%s without %s', $tags[0], $tags[1]));
				return [];
			}

			// from & to are found, return in convenient format
			return ['from' => $from, 'length' => ($to - $from) + strlen($tags[1])];
		}

		return [];
	}

	private function resolveForEach($text)
	{

		$positions = $this->findInnerMostTag($text, $this->foreachTags);

		while ($positions) {
			$placeHolder = substr($text, $positions['from'], $positions['length']);
			$expression = substr($placeHolder, strlen($this->foreachTags[0]), -strlen($this->foreachTags[1]));
			$parts = explode($this->foreachSeparator, $expression);
			if (count($parts) == 1) {
				$this->logError('foreach without body');
				return '';
			}

			$varNames = explode(' as ', $parts[0]);
			if (count($varNames) < 2) {
				$this->logError(sprintf("'%s' is not a valid foreach expression"));
				return '';
			}

			// isolate vaiable names, remove optional tags
			$rowsVariable = str_replace($this->varTags[0], '', $varNames[0]);
			$rowsVariable = trim(str_replace($this->varTags[1], '', $rowsVariable));
			$rowVariable = str_replace($this->varTags[0], '', $varNames[1]);
			$rowVariable = trim(str_replace($this->varTags[1], '', $rowVariable));

			array_shift($parts);
			// reconstruct template part
			$template = implode($this->foreachSeparator, $parts);
			$rows = $this->getValueFromArray($rowsVariable, $this->data);

			if (!is_array($rows)) {
				$this->logError(sprintf("'%s' is not an array"));
				return '';
			}

			$resolvedTemplate = '';

			foreach ($rows as $key->$value) {
				$resolvedLine = $template;
				$resolvedLine = str_replace($rowVariable, $rowsVariable . '.' . $key, $resolvedLine);
				$resolvedTemplate .= $resolvedLine;
			}

			$text = str_replace($placeHolder, $resolvedTemplate, $text);

			$positions = $this->findInnerMostTag($text, $this->foreachTags);
		}

		return $text;
	}

	private function resolveIf($text)
	{

		$this->error = '';

		$positions = $this->findInnerMostTag($text, $this->ifTags);

		while ($positions) {
			// extract the if expression from content text
			$placeHolder = substr($text, $positions['from'], $positions['length']);
			// remove tags to get expression
			$expression = substr($placeHolder, strlen($this->ifTags[0]), -strlen($this->ifTags[1]));
			$parts = explode($this->ifSeparator, $expression);

			// isolate varnames, remove optional tags
			$varName = str_replace($this->varTags[0], '', $parts[0]);
			$varName = trim(str_replace($this->varTags[1], '', $varName));
			$not = $varName[0] == '!';
			$varName = ltrim($varName, '!');

			array_shift($parts);
			// reconstruct template part
			$template = implode($this->ifSeparator, $parts);

			$value = $this->getValueFromArray($varName, $this->data);
			if($not){
				$value = !$value;
			}
			
			if ($value) {
				// expression is truthy, re-insert template
				$text = str_replace($placeHolder, $template, $text);
			} else {
				// expression is falsy, remove template
				$text = str_replace($placeHolder, '', $text);
			}
			$positions = $this->findInnerMostTag($text, $this->ifTags);
		}

		return $text;
	}

	private function resolveRemark($text)
	{

		$positions = $this->findInnerMostTag($text, $this->remarkTags);

		while ($positions) {
			// extract the remark from content text
			$placeHolder = substr($text, $positions['from'], $positions['length']);
			// remove it
			$text = str_replace($placeHolder, '', $text);
			$positions = $this->findInnerMostTag($text, $this->remarkTags);
		}

		return $text;
	}

	private function resolveVariables($text)
	{
		// resolve variables
		while (($pos1 = strpos($text, $this->varTags[0])) !== false) {

			$pos2 = strpos($text, $this->varTags[1]);
			if ($pos2 !== false) {
				$pos2 = $pos2 + strlen($this->varTags[1]) - 1;
			}
			$placeHolder = substr($text, $pos1, ($pos2 - $pos1) + 1);
			$varNames = explode('.', substr($placeHolder, strlen($this->varTags[0]), -strlen($this->varTags[1])));

			$value = $this->getValueFromArray($varNames, $this->data);

			if ($value) {
				$text = str_replace($placeHolder, $value, $text);
			} else {
				$text = str_replace($placeHolder, '', $text);
			}
		}

		return $text;
	}

	private function resolve($text)
	{
		$text = $this->resolveRemark($text);
		$text = $this->resolveIf($text);
		$text = $this->resolveForeach($text);
		$text = $this->resolveVariables($text);

		return $text;
	}

	public function load(array $data)
	{
		$this->data = $data;
		return $this;
	}

	public function config(array $config)
	{
		$this->varTags = $config['variable_tags'] ?? $this->varTags;
		$this->ifTags = $config['if_tags'] ?? $this->ifTags;
		$this->ifSeparator = $config['if_separator'] ?? $this->ifSeparator;
		$this->remarkTags = $config['remark_tags'] ?? $this->remarkTags;
		$this->foreachTags = $config['foreach_tags'] ?? $this->foreachTags;
		$this->foreachSeparator = $config['foreach_separator'] ?? $this->foreachSeparator;

		$this->logCallback = $config['log_callback'] ?? $this->logCallback;

		return $this;
	}

	public function render()
	{
		$text = null;
		$text = $this->resolve($this->content);
	
		return $text;
	}

	public function __construct($fileOrString)
	{
		
		if(is_file($fileOrString)){
			$this->file = $fileOrString;
			$this->content = file_get_contents($fileOrString);
		}
		else{
			$this->file = '';
			$this->content = $fileOrString;
		}
		
		$this->varTags = ['{{', '}}'];
		$this->foreachTags = ['[foreach]', '[/foreach]'];
		$this->foreachSeparator = '->';
		$this->ifTags = ['[if]', '[/if]'];
		$this->ifSeparator = '->';
		$this->remarkTags = ['[*]', '[/*]'];

		$this->logCallback = null;
	}
}
