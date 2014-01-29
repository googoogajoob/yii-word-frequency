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
 * The operational phases of this class are as follows:
 * 1) Initialization - specify the text sources and filtering requirements
 * 2) Gather words from the text sources into an array
 * 3) Filter out unwanted words 
 * 4) Replace words and/or patterns
 * 5) Generate a frequency count 
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
 * @todo refactor example
 *	$this->widget('application.extensions.APCExtensions.APCTagCloud', 
 *		array(
 *				'stringSource' => 'this is a sample string source',
 *				'arraySourceList' => array(
 *						array('this is another', array('example of a source', array('the array contains arrays and', array('strings'))))
 *				),
 *				'activeRecordSourceList' => array(
 *							array('model'=>$sampleActiveRecordModel, 'attribute'=>array('sampleFieldAttribute', 'fieldTwo')),
 *						),
 * 			'blacklistFile' => array(
 *					'blacklist_alphabet.txt',
 *					'blacklist_de.txt',
 *					'blacklist_en.txt',
 *					'blacklist_umlaut.txt',
 *				), 
 *				'blacklist' => array(
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
 * 
 */

class YiiWordFrequency extends CComponent
{

	/**
	* @var unique array of tags and thier frequency count as key-value pair. "tag" => FrequencyCount
	*/
	public $tagFrequencyList = array();

	/**
	* @var NON-Unique array of individual tags
	*/
	protected $internalTagList = array();

	/**
	* @var a one-dimensional array containing references to text sources
	* Each array element ultimately refers to a text string which will be parsed into the frequency list
	* The elements of this array can be one of the three following types:
	* 1) A text string 
	* 2) An array containing text strings or further arrays containing text strings (i.e. an array tree of strings)
	* 3) An object pair (active record and CDbCriteria) which will be queried internally in this class-object
	*    The CDbCriteria object shoulkd contain the desired search criteria and column names which contin the texts 
	*    to be accumulated.
	*/
	public $sourceList = array();
		
	/**
	* @var string delimiter used for converting(with the explode function) strings to tags 
	*/
	public $explosionDelimiter = ' ';
	 
	/**
	 * @var a one-dimensional array containing blacklist words
	 * A blacklist is ulimately an array of tags which will be removed from the source texts
	 * The blacklist references must be an array of individual words:
	 * The array depth can be arbitrarily deep as it is processed internally 
	 * with array_walk_recursive to retrieve all values present.
	 * 
	 * Possible Use Cases: Blacklist files can be maintained for words that should 
	 * be ignored (for example in English 'the', 'a', 'and' etc.), 
	 * they can also be maintained for different languages, different technical fields etc. 
	 */
	public $blacklist = array();
	
	/**
	 * @var a one-dimensional array of filenames which contain blacklist words
	 * A reference to a text file containing a list of words:
	 * The file must be located in the assets directory of this extension 
	 * and the words in the file must be one per line 
	 * @see $blacklist for a description of blacklists
	 * (several pre-defined lists are included, @see assets directory)
	 */
	public $blacklistFile = array();

	/**
	* @var a one-dimensional array containing references to regular expression blacklists
	* An array of regular expressions. Words form the source texts which matched a regular expressions
	* will be removed. 
	* @see $blacklist for a description of blacklists
	*/
	public $blacklistRegExp = array();

	/**
	* @var boolean whether or not the blacklist comparison should be case insensitive
	* Only valid for $blacklist. NOT valid for $blacklistRegexp.
	*/
	public $blacklistIgnoreCase = true;

	/**
	* @var array of key value search replace strings. Useful for eliminating punction marks from text
	*/
	public $substitutionList = array();
	
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
	
	/**
	 * 
	 */	
	function __construct()	{
		$this->extensionAssetUrl = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'assets';
	}

	public function addSource($newSource) {
		if (is_string($newSource) or is_array($newSource)) {
			$this->sourceList[] = $newSource;
		} else {
			Yii::log(Yii::t('yii','Invalid source type in "{method}". String or arry of strings expected.', array('{method}'=>__METHOD__)),CLogger::LEVEL_WARNING);			
		}
	}
	
	/**
	 * @todo more stringent tests, need to test for exact class types
	 */
	public function addDbSource($model, $criteria) {
		if (is_object($model) and is_object($criteria)) {
			$this->sourceList[] = array($model, $criteria);
		} else {
			Yii::log(Yii::t('yii','Invaled source type in "{method}". Active Record Model and CDbCriteria objects expected.', array('{method}'=>__METHOD__)),CLogger::LEVEL_WARNING);			
		}
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
	protected function accumulateTagsfromActiveRecords($arModel, $arCriteria) {
		$rows = $arModel->findAll($arCriteria);
		foreach ($rows as $rowk => $rowv) {
			$singleRow = $rowv->getAttributes(null);
			foreach ($singleRow as $k => $v) {
				$this->addStringToTagList($v);
			}
		}
	}
	
	/**
	 * Calls all functions to import word/tags from all import sources: string, array list, avtive records
	 * The three types of sources are distinguished by thier types: string, array, object
	 */
	public function accumulateTagsfromSources() {
		foreach ($this->sourceList as $v) {
			if (is_string($v)) {
				$this->addStringToTagList($v);
			} elseif (is_array($v)) {
				if (is_array($v[0])) {
					$this->accumulateTagsfromArrays($v);
				} elseif (is_object($v[0])) {
					$this->accumulateTagsfromActiveRecords($v[0], $v[1]);
				} else {
					throw new CException(Yii::t('yii', 'Invalid string source in class {class}.', array('{class}'=>__CLASS__)));
				}
			}
		}
	}

	/**
	 * removes items from $this->internalTagList which are in the $blacklist references
	 */
	protected function removeBlacklistItems() {
		// merge all blacklist values into one array
		$compositeBlacklist = array(); 
		array_walk_recursive($this->blacklist, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeBlacklist); 
		//remove blacklisted words
		$inputList = $this->internalTagList;
		if ($this->blacklistIgnoreCase) {
			$this->internalTagList = array_udiff($inputList, $compositeBlacklist, 'strcasecmp');
		} else {
			$this->internalTagList = array_udiff($inputList, $compositeBlacklist, 'strcmp');
		}
		
		return $this;
	}
	
	protected function removeBlacklistFileItems() {
		//read blacklist terms from blacklist asset files
		foreach ($this->blacklistFile as $v) {
			$this->blacklist[] = file($this->extensionAssetUrl . '/' . $v, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		}
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $blacklistRegExp references
	 */
	protected function removeBlacklistRegExpItems() {
	}

	public function removeAllBlacklistItems() {
		if (count($this->blacklist)) $this->removeBlackListItems();
		if (count($this->blacklistFile)) $this->removeBlackListItems();
		if (count($this->blacklistRegExp)) $this->removeBlackListRegExpItems();
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
	 * Performs all functions necessary to import tags from all import sources as well as perform
	 * all subsequent modifications
	 * The result is that the array $this->frequencyList is created which is then used to display the tag cloud
	 * @note new in APCTagCloud
	 * @toDo Locale
	 */
	public function generateTagList() {
		$this->accumulateTagsFromSources(); //accumulate individual tags (multiple occurances allowed) from the various sources
		$this->removeAllBlacklistItems();
		if ($this->removeNumeric) {
			$this->internalTagList = $this->removeNumericItems($this->internalTagList);
		}
		//count the occurances of each tag and create a unique array with count total
		//$wordCount = array_count_values($this->internalTagList);
		$this->tagFrequencyList = array_count_values($this->internalTagList);

		//translate wordcount format to the format needed for displaying the tags
		//foreach($wordCount as $k => $v) {
		//	$this->tagFrequencyList[$k] = array('weight' => $v);
		//}
		
		// sort the result (if necessary)
		setlocale(LC_COLLATE, 'de_DE@euro', 'de_DE', 'de');
		if ($this->sortTagList > 0) {
			ksort($this->tagFrequencyList, SORT_LOCALE_STRING);
		} elseif ($this->sortTagList < 0) {
			krsort($this->tagFrequencyList);
		}
	}
}