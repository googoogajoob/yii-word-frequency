<?php
/**
 * @author Andrew Potter <apc@andypotter.org>
 *
 *	This class provides the ability of accumulating a list of words/tags from multiple text sources
 * and generating from them an array which has a list of the unique words used and the number of times 
 * each word occurs in the original source texts.
 * 
 * The initial motivation for this class was for generating the data for a TagCloud 
 * (for example see @link http://www.yiiframework.com/extension/yiitagcloud)
 *
 *	Specifically this class can take input from three types of text sources and in addition 
 * provides several means of filtering thier content.
 * 
 *	Sources can be strings, arrays of strings and active record query results.
 * 
 *	Filting possibilites for modifying the input sources and resulting list include:
 *		- specification of the separation delimiter for separating strings
 *		- a blacklist of words that should not be included in the final tag list
 *		- blacklists can be used which are stored in files under the assets directory of this extension
 *		- a list of substitution characters and their replacements for modifying the input strings, 
 *			this was initialy intended for eliminating punctuation marks 
 *		- a numeric removal flag, removes values (words/tags) which are purely numerical 
 *			this together with a substitution list can be combined to remove dates and times
 *		- force the case of the tag cloud (upper lower, leave alone)
 *		- sort the tag list (ascending, descending, leave alone)
 *
 *	Usage example, from a view:
 *
 *	$this->widget('application.extensions.APCExtensions.APCTagCloud', 
 *		array(
 *				'stringSource' => 'this is a sample string source',
 *				'arraySourceList' => array(
 *						array('this is another', array('example of a source', array('the array contains arrays and', array('strings'))))
 *				),
 *				'activeRecordSourceList' => array(
 *							array('model'=>$sampleActiveRecordModel, 'attribute'=>array('sampleFieldAttribute', 'fieldTwo')),
 *						),
 * 			'blackListFile' => array(
 *					'blacklist_alphabet.txt',
 *					'blacklist_de.txt',
 *					'blacklist_en.txt',
 *					'blacklist_umlaut.txt',
 *				), 
 *				'blackList' => array(
 *							array('a', 'an', 'be', 'can', 'the', 'these'),
 *						),
 *				'substitutionList' => array(
 *							'(' => '',
 *					      ')' => '',
 *					      '"' => '',
 *					      "'" => '',
 *					      ':' => '',
 *					      '.' => '',
 *					      '?' => '',
 *					      ',' => '',
 *					      '-' => '',
 *					      '–' => '',
 *					      '/' => '',
 *					      ';' => '',
 *					      '!' => '',
 *					      ),
 *				'removeNumeric' => true,
 *				'forceCase' => 0,
 *				'sortTagList' => 0,
 *		)
 *	); * 
 */

class YiiWordFrequency extends CComponent
{
    /**
     * 	@var unique array of words/tags
     */
    protected $internalTagList = array();
    
    /**
     * 	@var string delimiter used for converting(with the explode function) strings to words/tags 
     */
    public $explosionDelimiter = ' ';
	 
    /**
     *	@var array of values/arrays containing words/tags to be prevented from being listed in the tag cloud
     *	The multidimensional array allows one to maintain separate blacklists and combine them for used
     *	in screening tags from the cloud. For instance blacklists could be maintained for words from
     *	different languages, different technical fields etc. The array depth can be arbitrarily deep as it is 
     *	processed internally with array_walk_recursive to retrieve all values present.
     */
    public $blackList = array();
    
    /**
     * @var array of filenames containing blacklist sets
     * see @link $blackList for explanation of blacklists
     * the files should contain a one-dimensional list of blacklist terms (i.e. one word per line)
     * the files should be located in the assets directory of this extension
     */
    public $blackListFile = array();
    
    /**
     * @var array of key value search replace strings. Useful for eliminating punction marks from text
     */
    public $substitutionList = array();
    
    /**
     * @var string, contains a string which will be parsed and each individual word will be added to the list
     */
    public $stringSource = '';
	 
    /**
     * @var array, contains an array of arrays, which contain strings of text. Each string will be parsed and each individual word will be added to the list
     */
    public $arraySourceList = array();
    
    /**
     * @var array, contains an array of arrays which describe Active Record Queries and field list for active records, each string from the resulting queries will be parsed for individual words which will be added to the list
     */
    public $activeRecordSourceList = array();
    
    /**
     * @var integer, negative = force lowercase, 0 = no changes made to case, positive = force uppercase
     */
    public $forceCase = 0; 
	 
    /**
     * @var boolean, true remove numeric strings (such as dates, times etc., only makes sense if punctuation is removed)
     */
    public $removeNumeric = false; 
	 
    /**
     * @var integer, -1 = sort alphabetical zA -> aA, 0 = natural unchanged order,  sort alphabetical aA -> zZ
     */
    public $sortTagList = 0; 
	 
    /**
     * @var string the URL for this extensions assets directory
     */
	 protected $extensionAssetUrl;

	 public $initTestFlag = false;
	/**
	 * 
	 */	
	public function init()	{
		$this->initTestFlag = true;
	}

	/**
	 * Adds words from $inputString into the class list of words/tags ($this->internalTagList) which is an array of individual words
	 * this is the only class method where elements are added to $this->internalTagList
	 * @param string $inputString list of words to be added as tags to $this->internalTagList
	 */
	protected function addStringToTagList($inputString) {
		$newWords = strtr($inputString, $this->substitutionList); //perform substitutions
		$wordsToAdd = explode($this->explosionDelimiter, $newWords); //explode string to an array
		//trim strings in array
		$trimmedWordList = array();
		foreach ($wordsToAdd as $v) {
			$tempv = trim($v);
			if (strlen($tempv)) {
				if ($this->forceCase > 0) {
					$tempv = strtoupper($tempv);
				} elseif ($this->forceCase < 0) {
					$tempv = strtolower($tempv);
				}
				$trimmedWordList[] = $tempv;
		  }
		}			
		$this->internalTagList = array_merge($this->internalTagList, $trimmedWordList); //accumulate to internal list
	}

	/**
	 * Adds words from an array contining strings into the class list of words/tags ($this->internalTagList) which is an array of individual words
	 * the array can have several levels and any tree structure. It is processed recursively. The strings contained
	 * in the array must not be individual words they can be strings with several words.
	 * @param array $arraySource list of words to be added as tags to $this->internalTagList
	 */
	protected function accumulateTagsfromArrays($arraySource) {
		array_walk_recursive($arraySource, array($this, "addStringToTagList"));
	}
	
	/**
	 * Adds words from a list of Active Records into the class list of words/tags ($this->internalTagList) which is an array of individual words
	 * $this->activeRecordSourceList is an array contining a list of arrays which have the two keys:
	 *		model: is the active record (e.g. the value returned by the findAll() function) 
	 *		attribute: another array with a list of attribute names from the active record, whose text values should be imported
	 */
	protected function accumulateTagsfromActiveRecords() {
		foreach ($this->activeRecordSourceList as $k => $v) {
			if (is_array($v['model'])) {
				foreach ($v['model'] as $ar) {
					$tempAttr=$ar->getAttributes($v['attribute']);
					$this->accumulateTagsFromArrays($tempAttr);
				}
			} else {
				$tempAttr=$v['model']->getAttributes($v['attribute']);
				$this->accumulateTagsFromArrays($tempAttr);
			}
		}
	}
	
	/**
	 * Calls all functions to import word/tags from all import sources: string, array list, avtive records
	 */
	protected function accumulateTagsfromSources() {
		$this->addStringToTagList($this->stringSource);
		$this->accumulateTagsfromArrays($this->arraySourceList);
		$this->accumulateTagsfromActiveRecords();
	}

	/**
	 * removes all items from $inputList which are in the $this->blackList list of arrays
	 * the comparison is case insensitive
	 * @param array $inputList, list of words/tabs which are to compared and removed if found in the $this->blackList arrays
	 * @return array $inputList stripped of items found in the $this->blackList arrays
	 */
	protected function removeBlackListItems($inputList) {
		//read blacklist terms from asset files
		foreach ($this->blackListFile as $v) {
			$this->blackList[] = file($this->extensionAssetUrl . '/' . $v, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		}
		// merge all blacklist values into one array
		$compositeBlacklist = array(); 
		array_walk_recursive($this->blackList, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeBlacklist); 
		//remove blacklisted words
		return array_udiff($inputList, $compositeBlacklist, 'strcasecmp');
	}
    
	/**
	 * removes all items from $inputList which are purely numeric
	 * the comparison is case insensitive
	 * @param array $inputLst, list of words/tabs which are to compared and removed if found to be numeric
	 * @return array $inputList stripped of items which are numeric 
	 * @todo need to refine logic to also remove "0"
	 */
	protected function removeNumericItems($inputList) {
		return array_filter($inputList, function($arg) { return intval($arg) == 0; });
	}
    
	/**
	 * Performs all functions necessary to import word/tags from all import sources as well as perform
	 * all subsequent modifications
	 * The result is that the array $this->arrTags is created which is then used to display the tag cloud
	 * @note new in APCTagCloud
	 * @toDo Locale
	 */
	public function generateTagArray() {
		$this->accumulateTagsFromSources(); //accumulate individual tags (multiple occurances allowed) from the various sources
		$this->internalTagList = $this->removeBlackListItems($this->internalTagList);
		if ($this->removeNumeric) {
			$this->internalTagList = $this->removeNumericItems($this->internalTagList);
		}
		$wordCount = array_count_values($this->internalTagList); //count the occurances of each word/tag and create a unique array with count total
		//translate wordcount format to the format needed for displaying the tags
		foreach($wordCount as $k => $v) {
			$this->arrTags[$k] = array('weight' => $v);
		}
		// sort the result (if necessary)
		setlocale(LC_COLLATE, 'de_DE@euro', 'de_DE', 'de');
		if ($this->sortTagList > 0) {
			ksort($this->arrTags, SORT_LOCALE_STRING);
		} elseif ($this->sortTagList < 0) {
			krsort($this->arrTags);
		}
	}
	
	/**
	 */	
	public function run() {
		$this->generateTagArray(); 
	}
}