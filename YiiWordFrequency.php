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
	* The goal of this class is to create this list
	*/
	public $tagFrequencyList = array();

	/**
	* @var NON-Unique array of individual tags that have been accumulated form all sources
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
	 * will be removed. The array depth can be arbitrarily deep as it is processed internally 
	 * with array_walk_recursive to retrieve all values present.
	 * @see $blacklist for a description of blacklists
	 */
	public $blacklistRegularExpression = array();

	/**
	 * @var a one-dimensional array of filenames which contain blacklist words
	 * A reference to a text file containing a list of regular expression:
	 * The file must be located in the assets directory of this extension 
	 * and the regularexpressions in the file must be one per line 
	 * @see $blacklist for a description of blacklists
	 * (several pre-defined lists are included, @see assets directory)
	 */
	public $blacklistRegularExpressionFile = array();

	/**
	* @var boolean whether or not the blacklist comparison should be case insensitive
	* Only valid for $blacklist. NOT valid for $blacklistRegularExpression or $blacklistRegularExpressionFile
	*/
	public $blacklistCaseSensitive = false;
	
	/**
	 *  The $whitelist* parameters operate analagous to the $blacklist* parameters
	 *  The difference is in the result. Whitelist values act as a positive filter. 
	 *  Only the values listed in the whitelist parameters will be counted in the frequency list
	 * @see description of the $blacklist parameters
	 */
	public $whitelist = array();
	public $whitelistFile = array();
	public $whitelistRegularExpression = array();
	public $whitelistRegularExpressionFile = array();
	public $whitelistCaseSensitive = false;

	/**
	* @var array of key value search and replace strings. Useful for eliminating punction marks from text, for example
	* The usage of setting the $substitutionList* parameters is analagous to $whiteList* and $blackList*. 
	* One exception, however, is that tree array structures are not supported. The Arrays must be a one-dimensional
	* Key=>value array. The Key is the text to be searched for (i.e matched against) and the value is the replacement text.
	* 
	* One major difference between the substitutiolist* üarameters and the others are that they must be PHP files, which
	* return a key/value array. They are also located in the substitution sub-directory under assets.
	*/
	public $substitutionlist = array();
	public $substitutionlistFile = array();
	public $substitutionlistRegularExpression = array();
	public $substitutionlistRegularExpressionFile = array();
	public $substitutionlistCaseSensitive = false;
	
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
		$wordsToAdd = explode($this->explosionDelimiter, $inputString); //explode string to an array
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
	 * model: is the active record (e.g. the value returned by the findAll() function) 
	 * attribute: another array with a list of attribute names from the active record, whose text values should be imported
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
	protected function blacklistFilter() {
		// merge all blacklist values into one array
		$compositeBlacklist = array(); 
		array_walk_recursive($this->blacklist, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeBlacklist); 
		$this->blacklistRemovalUtility($compositeBlacklist);
	}
	
	protected function blacklistFileFilter() {
		$compositeBlacklist = array();
		//read blacklist terms from blacklist asset files
		foreach ($this->blacklistFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				$compositeBlacklist = array_merge($compositeBlacklist, file($filePathName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			} else {
				throw new CException(Yii::t('yii', 'Blacklist File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->blacklistRemovalUtility($compositeBlacklist);
	}
	
	/**
	 *  Utility function for removing blacklisted items from a blacklist array
	 */
	protected function blacklistRemovalUtility($blacklist) {
		//remove blacklisted words
		$inputList = $this->internalTagList;
		if ($this->blacklistCaseSensitive) {
			$this->internalTagList = array_udiff($inputList, $blacklist, 'strcmp');
		} else {
			$this->internalTagList = array_udiff($inputList, $blacklist, 'strcasecmp');
		}
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $blacklistRegularExpression references
	 */
	protected function blacklistRegularExpressionFilter() {
		// merge all blacklist values into one array
		$compositeBlacklist = array(); 
		array_walk_recursive($this->blacklistRegularExpression, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeBlacklist); 
		$this->blacklistRegularExpressionRemovalUtility($compositeBlacklist);
	}

	/**
	 * removes items from $this->internalTagList which are in the $blacklistRegularExpressionFile references
	 */
	protected function blacklistRegularExpressionFileFilter() {
		$compositeBlacklist = array();
		//read blacklist terms from blacklist asset files
		foreach ($this->blacklistRegularExpressionFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				$compositeBlacklist = array_merge($compositeBlacklist, file($filePathName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			} else {
				throw new CException(Yii::t('yii', 'Blacklist Regular Expression File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->blacklistRegularExpressionRemovalUtility($compositeBlacklist);
	}

	/**
	 *  Utility function for removing matches to regular expressions
	 */
	protected function blacklistRegularExpressionRemovalUtility($regularExpressionList) {
		//remove blacklisted regular expression
		foreach ($regularExpressionList as $v) {
			$inputList = $this->internalTagList;
			$this->internalTagList = preg_grep ($v, $inputList, PREG_GREP_INVERT);
		}
	}

	public function blacklistFilterAll() {
		if (count($this->blacklist)) $this->blacklistFilter();
		if (count($this->blacklistFile)) $this->blacklistFileFilter();
		if (count($this->blacklistRegularExpression)) $this->blacklistRegularExpressionFilter();
		if (count($this->blacklistRegularExpressionFile)) $this->blacklistRegularExpressionFileFilter();
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $whitelist references
	 */
	protected function whitelistFilter() {
		// merge all whitelist values into one array
		$compositeWhitelist = array(); 
		array_walk_recursive($this->whitelist, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeWhitelist); 
		$this->whitelistRemovalUtility($compositeWhitelist);
	}
	
	protected function whitelistFileFilter() {
		$compositeWhitelist = array();
		//read whitelist terms from whitelist asset files
		foreach ($this->whitelistFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				$compositeWhitelist = array_merge($compositeWhitelist, file($filePathName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			} else {
				throw new CException(Yii::t('yii', 'Whitelist File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->whitelistRemovalUtility($compositeWhitelist);
	}
	
	/**
	 *  Utility function for removing whitelisted items from a whitelist array
	 */
	protected function whitelistRemovalUtility($whitelist) {
		//remove whitelisted words
		$inputList = $this->internalTagList;
		if ($this->whitelistCaseSensitive) {
			$this->internalTagList = array_uintersect($inputList, $whitelist, 'strcmp');
		} else {
			$this->internalTagList = array_uintersect($inputList, $whitelist, 'strcasecmp');
		}
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $whitelistRegularExpression references
	 */
	protected function whitelistRegularExpressionFilter() {
		// merge all whitelist values into one array
		$compositeWhitelist = array(); 
		array_walk_recursive($this->whitelistRegularExpression, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeWhitelist); 
		$this->whitelistRegularExpressionRemovalUtility($compositeWhitelist, true);
	}

	/**
	 * removes items from $this->internalTagList which are in the $whitelistRegularExpressionFile references
	 */
	protected function whitelistRegularExpressionFileFilter() {
		$compositeWhitelist = array();
		//read whitelist terms from whitelist asset files
		foreach ($this->whitelistRegularExpressionFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				$compositeWhitelist = array_merge($compositeWhitelist, file($filePathName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			} else {
				throw new CException(Yii::t('yii', 'Whitelist Regular Expression File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->whitelistRegularExpressionRemovalUtility($compositeWhitelist, true);
	}

	/**
	 *  Utility function for removing matches to regular expressions
	 */
	protected function whitelistRegularExpressionRemovalUtility($regularExpressionList) {
		// Whenever an array element is include it must be removed from further whitelist 
		// Tests, otherwise elements may be counted multiple times.
		// Thus inputList starts with the full selection of words and is succesively reduced
		$inputList = $this->internalTagList; //initialize, list to compare against
		$partialList = array(); //initialize, list of found items
		foreach ($regularExpressionList as $v) {
			$matchingElements = preg_grep ($v, $inputList); // Find items in list which match the regular expression
			$partialList = array_merge($partialList, $matchingElements); //Add matching items to the growing list of found items
			$inputList = array_diff($inputList, $matchingElements); //remove matching elements from list to be searched
		}
		$this->internalTagList = $partialList;
	}

	public function whitelistFilterAll() {
		if (count($this->whitelist)) $this->whitelistFilter();
		if (count($this->whitelistFile)) $this->whitelistFileFilter();
		if (count($this->whitelistRegularExpression)) $this->whitelistRegularExpressionFilter();
		if (count($this->whitelistRegularExpressionFile)) $this->whitelistRegularExpressionFileFilter();
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $substitutionlist references
	 */
	protected function substitutionlistFilter() {
		$this->substitutionlistRemovalUtility($this->substitutionlist);
	}
	
	protected function substitutionlistFileFilter() {
		$compositeSubstitutionlist = array();
		//read substitutionlist terms from substitutionlist asset files
		foreach ($this->substitutionlistFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				foreach(require($filePathName) as $k => $v) {
					$compositeSubstitutionlist[$k] = $v;
				}
			} else {
				throw new CException(Yii::t('yii', 'Substitutionlist File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->substitutionlistRemovalUtility($compositeSubstitutionlist);
	}
	
	/**
	 *  Utility function for removing substitutionlisted items from a substitutionlist array
	 */
	protected function substitutionlistRemovalUtility($substitutionlist) {
		if ($this->substitutionlistCaseSensitive) {
			$this->internalTagList = str_ireplace(
				array_keys($substitutionlist), 
				array_values($substitutionlist), 
				$this->internalTagList);
		} else {
			$this->internalTagList = str_replace(
				array_keys($substitutionlist), 
				array_values($substitutionlist), 
				$this->internalTagList);
		}
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $substitutionlistRegularExpression references
	 */
	protected function substitutionlistRegularExpressionFilter() {
		$this->substitutionlistRemovalUtility($this->substitutionlistRegularExpression);
	}

	/**
	 * removes items from $this->internalTagList which are in the $substitutionlistRegularExpressionFile references
	 */
	protected function substitutionlistRegularExpressionFileFilter() {
		$compositeSubstitutionlist = array();
		//read substitutionlist terms from substitutionlist asset files
		foreach ($this->substitutionlistRegularExpressionFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				$compositeSubstitutionlist = array_merge($compositeSubstitutionlist, file($filePathName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			} else {
				throw new CException(Yii::t('yii', 'Substitutionlist Regular Expression File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->substitutionlistRegularExpressionRemovalUtility($compositeSubstitutionlist, true);
	}

	/**
	 *  Utility function for removing matches to regular expressions
	 */
	protected function substitutionlistRegularExpressionRemovalUtility($regularExpressionList) {
		// Whenever an array element is include it must be removed from further substitutionlist 
		// Tests, otherwise elements may be counted multiple times.
		// Thus inputList starts with the full selection of words and is succesively reduced
		$inputList = $this->internalTagList; //initialize, list to compare against
		$partialList = array(); //initialize, list of found items
		foreach ($regularExpressionList as $v) {
			$matchingElements = preg_grep ($v, $inputList); // Find items in list which match the regular expression
			$partialList = array_merge($partialList, $matchingElements); //Add matching items to the growing list of found items
			$inputList = array_diff($inputList, $matchingElements); //remove matching elements from list to be searched
		}
		$this->internalTagList = $partialList;
	}

	public function substitutionlistFilterAll() {
		if (count($this->substitutionlist)) $this->substitutionlistFilter();
		if (count($this->substitutionlistFile)) $this->substitutionlistFileFilter();
		if (count($this->substitutionlistRegularExpression)) $this->substitutionlistRegularExpressionFilter();
		if (count($this->substitutionlistRegularExpressionFile)) $this->substitutionlistRegularExpressionFileFilter();
	}

	/**
	 * removes all items from $inputList which are purely numeric
	 * the comparison is case insensitive
	 * @param array $inputList, list of words/tabs which are to compared and removed if found to be numeric
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
		$this->blacklistFilterAll();
		$this->whitelistFilterAll();
		$this->substitutionlistFilterAll();
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