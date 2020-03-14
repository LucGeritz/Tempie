# Tempie

>Tempie is work in progress. It does work already though..

I love template engines for PHP.
At the same time I believe template engines should be small and humble.
Tempie represents the minimal (and maybe maximal) functionality a template engine should offer

- variable resolving
- iterations
- selections
- comments
- filters

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

Then `$tempie->load($data)` is used to load the data in the template. This data is expected to be an associative array. In the template you can reference to the key of the array, Tempie will replace it by its value.

## Nested arrays
Nested arrays work as well. Use a period to refer to a subelement. The earlier example could be rewritten as such..

````php
$tempie = new Tigrez\Tempie('The train to {{destination.city}} leaves from platform {{destination.platform}}');

$tempie->load(['destination' => ['city'=>'Paris', 'platform' => '11b']]);

echo($tempie->render());
````

## Selections (If)

An *if* looks like this..

````html
[if]
    passed -> <div>Congratulations! You passed!</div>
[/if]
````

in general it's

    [if] condition -> text [/if]

*condition* is a variable, if the condition is true (or more precise 'truthy') *text* is rendered. If false (falsy) it is ignored.

You can negate the condition by prefixing it with a !

````html
[if]
    !passed -> <div>You failed, better luck next time!</div>
[/if]
````

Because of its context tempie knows the condition is a reference to a variable making the use of brackets unnecessary.
If (e.g. for reasons of consistency) you want to use them you can.

````html
[if]
    {{!passed}} -> <div>You failed, better luck next time!</div>
[/if]
````

## Iterations (Foreach)

A *foreach* looks like this..

````
[foreach]
users as user ->
    Name: {{user.name}}
    Age : {{user.age}}
[/foreach]
````

In the example `users` is the variable name of the array. This array is iterated by Tempie and each iteration can be refered to by `user`.

If the data would be

    [
        'users' => [
            ['name' => 'Herbert', 'age' => 45],
            ['name' => 'Katja', 'age' => 52],
            ['name' => 'Sue Ann', 'age' => 27]
        ]

    ];

the result would be

    Name: Herbert
    Age : 45

    Name: Katja
    Age : 52

    Name: Sue Ann
    Age : 27

Again, brackets for the array name and variable name before the `->` are optional.


## Comments

Comments look like this..

````
[*] This is
a multiline comment [/*]
````
Comments will be removed from the resolved template.

## Filters

## Permanent Filters

## Config

## Error log

## The Tempie Factory

