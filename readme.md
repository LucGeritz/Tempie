# Tempie

>Tempie is work in progress. It does work already though..

I love template engines for PHP. 
At the same time I believe template engines should be small and humble.
Tempie is what I think I template engine should offer

- an easy to read syntax
- variable resolving 
- iterations 
- selections 
- comments 

## A most basic example

````php
$tempie = new Tigrez\Tempie('The train to {{city}} leaves from platform {{platform}}');

$tempie->load(['city' => 'Paris', 'platform' => '11b']);

echo($tempie->render());
````
this will echo:

    The train to Paris leaves from platform 11b

So what happened?  
First an instance of tempie was created. The constructor parameter can either be a string (like in the example) or, more useful in the real world, the file name of a template file. 

Then `$tempie->load($data)` is used to load the data in the template. This data is expected to be an associative array. In the template you can reference to the key of the array, Templie will replace it by its value. 

## Nested arrays
Nested arrays work as well. Use a period to refer to a subelement. The earlier example could be rewritten as such..

````php
$tempie = new Tigrez\Tempie('The train to {{destination.city}} leaves from platform {{destination.platform}}');

$tempie->load(['destination' => ['city'=>'Paris', 'platform' => '11b']]);

echo($tempie->render());
````

## If

An *if* looks like this..

````html
[if]
    {{passed}} -> <div>Congratulations! You passed!</div>
[/if]
````

in general it's 

    [if] condition -> text [/if]

*condition* is a variable, if the condition is true (or more precise 'truthy') *text* is shown. If false (falsy) it is ignored.

You can negate the condition by prefixing it with a !

````html
[if]
    {{!passed}} -> <div>You failed, better luck next time!</div>
[/if]
````
## Foreach

## Comments
