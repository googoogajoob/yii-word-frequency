#yii-word-frequency

A class for accumulating tokens from various text sources, filtering them, and counting their frequency. 
It is intended for the Yii 1.X CMS

##Potential Usage
* Generating Tag Clouds
* Determining the top keywords in a set of texts (e.g. Most often mentioned keywords in a set of posts or comments)
* Determining the frequency of a specified set of tokens (e.g. A sports blog displays which players from a specific team appear in a post)

##Installation
Place the contents of the 'yii-word-frequency' extension (extracted from the zip file 
or obtained via Github) into the extensions folder extensions/yii-word-frequency 

Add **ext.yii-word-frequency** to the import array in **config/main.php**
~~~	
	// preloading 'log' component
	'preload'=>array('log'),

	// autoloading model and component classes
	'import'=>array(
		'application.models.*',
		'application.components.*',
		'ext.yii-word-frequency.*', 
	),
~~~

##Usage
###A minimalistic example
~~~
$ywf = Yii::createComponent(array('class' => 'YiiWordFrequency'));
$ywf->sourceList = 'This is a test string. This is another test string. Test strings are fun.';
$ywf->accumulateSources();
$frequencyList = $ywf->generateList();
~~~

###An example using Active Records 
(the frequency list can be obtained from the return value of generateList() as above 
 or via the property 

	$ywf = Yii::createComponent(array('class' => 'YiiWordFrequency'));
	$model = new Testdata;
	$criteria=new CDbCriteria();
	$criteria->addInCondition('id',array(1,2)); 
	$criteria->select = "col1, col2, col3";
	$ywf->sourceList = array(array($model, $criteria));
	$ywf->accumulateSources();
	$ywf->generateList();
	$frequencyList = $ywf->tagFrequencyList;

###An example using multiple sources

	$ywf = Yii::createComponent(array('class' => 'YiiWordFrequency'));
	$model = new Testdata; // Active Record Model
	$criteria=new CDbCriteria(); // Criteria object for determining columns and record sources
	$criteria->addInCondition('id',array(1)); 
	$criteria->select = "col1";
	$this->ywf->sourceList = array(
		$this->inputFixture[0],
		$this->inputFixture[1],
		array($model, $criteria),
	);
	$this->ywf->accumulateSources();
	$this->ywf->generateList();
	$frequencyList = $ywf->tagFrequencyList;

###An example using a blacklist and method chaining 

	$ywf = Yii::createComponent(array('class' => 'YiiWordFrequency'));
	$ywf->sourceList = 'This is a test string. This is another test string. Test strings are fun.';
	$ywf->blackList = array('this', 'is');
	$ywf->accumulateSources()->runBlackListFilter()->generateList();
	$frequencyList = $ywf->tagFrequencyList;

###An example using a whitelist as well as configuration at object creation 

	$this->ywf = Yii::createComponent(array(
		'class' => 'YiiWordFrequency',
		'sourceList'=> array($this->inputFixture[0]),
		'whiteListFile' => array('../tests/fixtures/whiteList_test.txt'),
		'whiteListCaseSensitive' => true,
		)
	);
	$this->ywf->accumulateSources()->runWhiteListFilter()->generateList();
	print_r($this->ywf->tagFrequencyList);

More examples can be found in the file tests/unit/YiiWordFrequencyTest.php

##Description

This class provides the ability of accumulating a list of tokens (words, tags, etc.) from 
multiple text sources and generating from them an array which has a unique list of tokens used 
as well as the number of times each token occurs in the original source texts.

The initial motivation for this class was to generating data for a TagCloud which 
could be displayed with the yiitagcloud widget http://www.yiiframework.com/extension/yiitagcloud

This class can take input from several types of text sources and provides various means 
of filtering their content (i.e inclusion or exclusion in the final list) as well as some 
basic manipulation of the data.

###Possible Sources
The basic source for tokens is the text string. The text strings, however, can be retrieved from 
multiple types. Except for a simple text string all of them can be specified multiple times.
* The simplest form is a single text string.
* An array of string values
* An array of arrays which resolves to strings at the end node
* An active record object accompanied by a query defining which records and columns to use 
* A text file

Individual tokens are extracted from the sources based on a delimiter which defaults to
space but can be otherwise specified.

###Filtering Options - Blacklist
A blacklist is an array of tokens which act as a negative filter. Tokens which have been extracted
from source texts are checked against the blacklists. If there is a match the token will be removed.
This is useful for eliminating sets of words which should not be counted (such as articles 
and conjunctions in English texts. e.g. a, an, the, this, and, or).
Blacklists can be defined in the form of an array, from a file and as a regular expression. 
In addition the blacklist matching comparison can be case insensitive.

###Filtering Options - Whitelist
A whitelist is an array of tokens which act as a positive filter. Tokens which have been extracted
from source texts are checked against the whitelists. Only if there is a match will the the 
token be counted. A potential use for this is to count the frequency of a specific set of words
in a set of texts. 
Whitelist usage is analogous to blacklists. They can be defined in the form of an array, from 
a file and as a regular expression. In addition whitelist matching comparison can be case insensitive.

###Filtering Options - Substitution
A substitutionlist is a list of tokens and associated replacement values. Tokens which have been 
extracted from source texts can be modified according to multiple substitution lists. The lists are 
specified as key=>value pairs, where the key is the token to be searched for and the value is the
replacement value. The specification of whitelists is analogous to blacklists and whitelists with
one major difference. A substitution list in a file must be a PHP snippet which returns a key->value 
array. Substitution lists can include regular expressions and has an option for case insensitivity.
One possible use for substation is to filter out punctuation symbols by replacing them with an empty string.
Another possible use would be to remove or replace URLs in a text. 

In all three filtering options (blacklist, whitelist and substitutionlist) the case insensitive option 
is ignored for regular expressions, as case insensitive behavior can be specified
in the regular expression itself.

###Additional Options
The resulting token list can be modified in four possible ways
* Numerical tokens can be removed, integer values only (including 0)
* The case of the tokens can be forced to upper or lowercase
* The list can be sorted by token (and a sort_locale can be specified)
* The list can be sorted by the token frequency
 
###Existing Data
The assets directory of this extension is where this class looks for blacklists, whitelists and
substitution lists. This can be specified by altering the value of the property $extensionAssetUrl. 
This assets directory should not be confused with the Yii assets directory which contain CSS or 
JavaScript files among others. The assets for YiiWordFrequency (this class) are not required to 
be accessible by the browser. They are only needed by PHP on the server and thus can exist outside
of the webroot directory.
Delivered with this extension are four blacklists and a substitution list. 
* blackList_alphabet.txt 	- single characters of the English alphabet
* blackList_de.txt			- German words that should not be included in a tag cloud
* blackList_en.txt			- English words that should not be included in a tag cloud
* blackList_umlaut.txt		- German special characters, extension to blacklist_alphabet for German texts
* punctuation_en.php		- substitution list for elimination punctuation characters
 
Additional examples of blacklists, whitelists and subsitutionlists can be found in the tests/fixtures
directory of this extension.
 
###More Usage
There are four operational phases when using objects of this class
* Initialization - create object and specify all sources, filtering lists and additional options
* Accumulate the token for the specified sources
* Perform filtering options
* Generate the token frequency list 

After creation and specification the accumulation must take place. Generation of the list must also 
be done last. The filtering options offer flexibility. They can be formed in differing orders. 
Blacklists, for example, may have a different effect on the list of tokens, if a substitution
was performed beforehand. Dates of the format 12/07/2014 could be eliminated by replacing the slash 
with empty text and then removing numeric items. Or, optionally, they could be removed with regular
expression. In order to accommodate all the possibilities and flexibility for the filtering options.
The filtering methods must be explicitly called for the object. If filtering options have been defined
but the filter is not been called a warning will be given during the generation phase.
Also the filtering methods are chainable so that all the necessary calls to the object can 
be done within a minimal number of code lines.
