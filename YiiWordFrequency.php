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
 * 	- All regular expression filtering is performed using preg_grep, preg_replace() 
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
 * 			'blackListFile' => array(
 *					'blackList_alphabet.txt',
 *					'blackList_de.txt',
 *					'blackList_en.txt',
 *					'blackList_umlaut.txt',
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
	public $blackList = array();
	
	/**
	 * @var a one-dimensional array of filenames which contain blacklist words
	 * A reference to a text file containing a list of words:
	 * The file must be located in the assets directory of this extension 
	 * and the words in the file must be one per line 
	 * @see $blackList for a description of blacklists
	 * (several pre-defined lists are included, @see assets directory)
	 */
	public $blackListFile = array();

	/**
	 * @var a one-dimensional array containing references to regular expression blacklists
	 * An array of regular expressions. Words form the source texts which matched a regular expressions
	 * will be removed. The array depth can be arbitrarily deep as it is processed internally 
	 * with array_walk_recursive to retrieve all values present.
	 * @see $blackList for a description of blacklists
	 */
	public $blackListRegularExpression = array();

	/**
	 * @var a one-dimensional array of filenames which contain blacklist words
	 * A reference to a text file containing a list of regular expression:
	 * The file must be located in the assets directory of this extension 
	 * and the regularexpressions in the file must be one per line 
	 * @see $blackList for a description of blacklists
	 * (several pre-defined lists are included, @see assets directory)
	 */
	public $blackListRegularExpressionFile = array();

	/**
	* @var boolean whether or not the blacklist comparison should be case insensitive
	* Only valid for $blackList. NOT valid for $blackListRegularExpression or $blackListRegularExpressionFile
	*/
	public $blackListCaseSensitive = false;
	
	/**
	 * The $whiteList* parameters operate analagous to the $blackList* parameters
	 * The difference is in the result. Whitelist values act as a positive filter. 
	 * Only the values listed in the whitelist parameters will be counted in the frequency list
	 * @see description of the $blackList parameters
	 */
	public $whiteList = array();
	public $whiteListFile = array();
	public $whiteListRegularExpression = array();
	public $whiteListRegularExpressionFile = array();
	public $whiteListCaseSensitive = false;

	/**
	* @var array of key value search and replace strings. Useful for eliminating punction marks from text, for example
	* The usage of setting the $substitutionList* parameters is analagous to $whiteList* and $blackList*. 
	* One exception, however, is that tree array structures are not supported. The Arrays must be a one-dimensional
	* Key=>value array. The Key is the text to be searched for (i.e matched against) and the value is the replacement text.
	* 
	* One major difference between the substitutioList* parameters and the others are that they must be PHP files, which
	* return a key/value array.
	*/
	public $substitutionList = array();
	public $substitutionListFile = array();
	public $substitutionListRegularExpression = array();
	public $substitutionListRegularExpressionFile = array();
	public $substitutionListCaseSensitive = false;
	
	/**
	* @var integer, negative = force lowercase, 0 = no changes made to case, positive = force uppercase
	*/
	public $forceCase = 0; 
	
	/**
	* @var boolean, true remove numeric strings (such as dates, times etc., only makes sense if punctuation is removed first)
	*/
	public $removeNumeric = false; 
	
	/**
	* @var integer, -1 = sort alphabetical zA -> aA, 0 = natural unchanged order,  sort alphabetical aA -> zZ
	*/
	public $sortTagList = 0; 
	 
	/**
	* @var string the URL for this extensions assets directory
	*/
	public $extensionAssetUrl;
	
	/**
	* @var boolean flag indication if the filters or methods have been called
	*/
	protected $accumulateVisited = false;
	protected $blackListVisited = false;
	protected $whiteListVisited = false;
	protected $substitutionListVisited = false;
	
	public function __construct() {
		$this->extensionAssetUrl = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
	}
	
	public function addSource($newSource) {
		if (is_string($newSource) or is_array($newSource)) {
			$this->sourceList[] = $newSource;
		} else {
			Yii::log(Yii::t('yii','Invalid source type in "{method}". String or array of strings expected.', array('{method}'=>__METHOD__)),CLogger::LEVEL_WARNING);
		}
		
		return $this;
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
		return $this;
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
	protected function accumulateFromArrays($arraySource) {
		array_walk_recursive($arraySource, array($this, "addStringToTagList"));
	}

	/**
	 * Adds words from a list of Active Records into the class list of words/tags ($this->internalTagList) which is an array of individual words
	 * $this->activeRecordSourceList is an array contining a list of arrays which have the two keys:
	 * model: is the active record (e.g. the value returned by the findAll() function) 
	 * attribute: another array with a list of attribute names from the active record, whose text values should be imported
	 */
	protected function accumulateFromActiveRecords($arModel, $arCriteria) {
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
	public function accumulateSources() {
		$this->accumulateVisited = true;
		foreach ($this->sourceList as $v) {
			if (is_string($v)) {
				$this->addStringToTagList($v);
			} elseif (is_array($v)) {
				if (is_array($v[0])) {
					$this->accumulateFromArrays($v);
				} elseif (is_object($v[0])) {
					$this->accumulateFromActiveRecords($v[0], $v[1]);
				} else {
					throw new CException(Yii::t('yii', 'Invalid string source in class {class}.', array('{class}'=>__CLASS__)));
				}
			}
		}
		return $this;		
	}

	/**
	 * removes items from $this->internalTagList which are in the $blackList references
	 */
	protected function blackListFilter() {
		// merge all blacklist values into one array
		$compositeBlackList = array(); 
		array_walk_recursive($this->blackList, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeBlackList); 
		$this->blackListRemovalUtility($compositeBlackList);
	}
	
	protected function blackListFileFilter() {
		$compositeBlackList = array();
		//read blacklist terms from blackList asset files
		foreach ($this->blackListFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				$compositeBlackList = array_merge($compositeBlackList, file($filePathName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			} else {
				throw new CException(Yii::t('yii', 'Blacklist File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->blackListRemovalUtility($compositeBlackList);
	}
	
	/**
	 *  Utility function for removing blacklisted items from a blacklist array
	 */
	protected function blackListRemovalUtility($blackList) {
		//remove blacklisted words
		$inputList = $this->internalTagList;
		if ($this->blackListCaseSensitive) {
			$this->internalTagList = array_udiff($inputList, $blackList, 'strcmp');
		} else {
			$this->internalTagList = array_udiff($inputList, $blackList, 'strcasecmp');
		}
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $blackListRegularExpression references
	 */
	protected function blackListRegularExpressionFilter() {
		// merge all blacklist values into one array
		$compositeBlackList = array(); 
		array_walk_recursive($this->blackListRegularExpression, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeBlackList); 
		$this->blackListRegularExpressionRemovalUtility($compositeBlackList);
	}

	/**
	 * removes items from $this->internalTagList which are in the $blacklistRegularExpressionFile references
	 */
	protected function blackListRegularExpressionFileFilter() {
		$compositeBlackList = array();
		//read blacklist terms from blacklist asset files
		foreach ($this->blackListRegularExpressionFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				$compositeBlackList = array_merge($compositeBlackList, file($filePathName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			} else {
				throw new CException(Yii::t('yii', 'Blacklist Regular Expression File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->blackListRegularExpressionRemovalUtility($compositeBlackList);
	}

	/**
	 *  Utility function for removing matches to regular expressions
	 */
	protected function blackListRegularExpressionRemovalUtility($regularExpressionList) {
		//remove blacklisted regular expression
		foreach ($regularExpressionList as $v) {
			$inputList = $this->internalTagList;
			$this->internalTagList = preg_grep ($v, $inputList, PREG_GREP_INVERT);
		}
	}

	public function runBlackListFilter() {
		$this->blackListVisited = true;
		if (count($this->blackList)) $this->blackListFilter();
		if (count($this->blackListFile)) $this->blackListFileFilter();
		if (count($this->blackListRegularExpression)) $this->blackListRegularExpressionFilter();
		if (count($this->blackListRegularExpressionFile)) $this->blackListRegularExpressionFileFilter();

		return $this;
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $whiteList references
	 */
	protected function whiteListFilter() {
		// merge all whitelist values into one array
		$compositeWhiteList = array(); 
		array_walk_recursive($this->whiteList, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeWhiteList); 
		$this->whiteListRemovalUtility($compositeWhiteList);
	}
	
	protected function whiteListFileFilter() {
		$compositeWhiteList = array();
		//read whitelist terms from whitelist asset files
		foreach ($this->whiteListFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				$compositeWhiteList = array_merge($compositeWhiteList, file($filePathName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			} else {
				throw new CException(Yii::t('yii', 'Whitelist File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->whiteListRemovalUtility($compositeWhiteList);
	}
	
	/**
	 *  Utility function for removing whitelisted items from a whitelist array
	 */
	protected function whiteListRemovalUtility($whiteList) {
		//remove whitelisted words
		$inputList = $this->internalTagList;
		if ($this->whiteListCaseSensitive) {
			$this->internalTagList = array_uintersect($inputList, $whiteList, 'strcmp');
		} else {
			$this->internalTagList = array_uintersect($inputList, $whiteList, 'strcasecmp');
		}
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $whiteListRegularExpression references
	 */
	protected function whiteListRegularExpressionFilter() {
		// merge all whitelist values into one array
		$compositeWhiteList = array(); 
		array_walk_recursive($this->whiteListRegularExpression, create_function('$val, $key, $obj', 'array_push($obj, $val);'), &$compositeWhiteList); 
		$this->whiteListRegularExpressionRemovalUtility($compositeWhiteList, true);
	}

	/**
	 * removes items from $this->internalTagList which are in the $whiteListRegularExpressionFile references
	 */
	protected function whiteListRegularExpressionFileFilter() {
		$compositeWhiteList = array();
		//read whitelist terms from whiteList asset files
		foreach ($this->whiteListRegularExpressionFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				$compositeWhiteList = array_merge($compositeWhiteList, file($filePathName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			} else {
				throw new CException(Yii::t('yii', 'Whitelist Regular Expression File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->whiteListRegularExpressionRemovalUtility($compositeWhiteList, true);
	}

	/**
	 *  Utility function for removing matches to regular expressions
	 */
	protected function whiteListRegularExpressionRemovalUtility($regularExpressionList) {
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

	public function runWhiteListFilter() {
		$this->whiteListVisited = true;
		if (count($this->whiteList)) $this->whiteListFilter();
		if (count($this->whiteListFile)) $this->whiteListFileFilter();
		if (count($this->whiteListRegularExpression)) $this->whiteListRegularExpressionFilter();
		if (count($this->whiteListRegularExpressionFile)) $this->whiteListRegularExpressionFileFilter();

		return $this;
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $substitutionlist references
	 */
	protected function substitutionListFilter() {
		$this->substitutionListRemovalUtility($this->substitutionList);
	}
	
	protected function substitutionListFileFilter() {
		$compositeSubstitutionList = array();
		//read substitutionlist terms from substitutionlist asset files
		foreach ($this->substitutionListFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				foreach(require($filePathName) as $k => $v) {
					$compositeSubstitutionList[$k] = $v;
				}
			} else {
				throw new CException(Yii::t('yii', 'Substitutionlist File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->substitutionListRemovalUtility($compositeSubstitutionList);
	}
	
	/**
	 *  Utility function for removing substitutionlisted items from a substitutionList array
	 */
	protected function substitutionListRemovalUtility($substitutionList) {
		if ($this->substitutionListCaseSensitive) {
			$this->internalTagList = str_ireplace(
				array_keys($substitutionList), 
				array_values($substitutionList), 
				$this->internalTagList);
		} else {
			$this->internalTagList = str_replace(
				array_keys($substitutionList), 
				array_values($substitutionList), 
				$this->internalTagList);
		}
	}
	
	/**
	 * removes items from $this->internalTagList which are in the $substitutionlistRegularExpression references
	 */
	protected function substitutionListRegularExpressionFilter() {
		$this->substitutionListRegularExpressionRemovalUtility($this->substitutionListRegularExpression);
	}

	/**
	 * removes items from $this->internalTagList which are in the $substitutionListRegularExpressionFile references
	 */
	protected function substitutionListRegularExpressionFileFilter() {
		$compositeSubstitutionList = array();
		//read substitutionlist terms from substitutionlist asset files
		foreach ($this->substitutionListRegularExpressionFile as $v) {
			$filePathName = $this->extensionAssetUrl . DIRECTORY_SEPARATOR . $v;
			if (file_exists($filePathName)) {
				foreach(require($filePathName) as $k => $v) {
					$compositeSubstitutionList[$k] = $v;
				}
			} else {
				throw new CException(Yii::t('yii', 'Substitutionlist Regular Expression File "{file}" not found in {method}.',
											array('{method}'=>__METHOD__, '{file}'=>$filePathName)));
			}
		}
		$this->substitutionListRegularExpressionRemovalUtility($compositeSubstitutionList);
	}

	/**
	 *  Utility function for removing matches to regular expressions
	 */
	protected function substitutionListRegularExpressionRemovalUtility($regularExpressionList) {
		$this->internalTagList = preg_replace(
			array_keys($regularExpressionList), 
			array_values($regularExpressionList), 
			$this->internalTagList);
	}

	public function runSubstitutionListFilter() {
		$this->substitutionListVisited = true;
		if (count($this->substitutionList)) $this->substitutionListFilter();
		if (count($this->substitutionListFile)) $this->substitutionListFileFilter();
		if (count($this->substitutionListRegularExpression)) $this->substitutionListRegularExpressionFilter();
		if (count($this->substitutionListRegularExpressionFile)) $this->substitutionListRegularExpressionFileFilter();

		return $this;
	}

	/**
	 * removes all items from $inputList which are purely numeric
	 * the comparison is case insensitive
	 * @param array $inputList, list of words/tabs which are to compared and removed if found to be numeric
	 * @return array $inputList stripped of items which are numeric 
	 * @todo need to refine logic to also remove "0"
	 */
	protected function removeNumericItems($inputList) {
		return array_filter($inputList, function($arg) { return !(intval($arg) > 0 or $arg == '0'); });
	}

	protected function issueUsageWarnings() {
		if (!$this->accumulateVisited) {
			Yii::log(Yii::t('yii','Sources have not been accumulated in "{class}".', array('{class}'=>__CLASS__)),CLogger::LEVEL_WARNING);			
		}
		
		if (count($this->sourceList == 0)) {
			Yii::log(Yii::t('yii','No sources defined in "{class}".', array('{class}'=>__CLASS__)),CLogger::LEVEL_WARNING);			
		}
	
		if (count($this->internalTagList) == 0) {
			Yii::log(Yii::t('yii','Sources have produced no results "{class}".', array('{class}'=>__CLASS__)),CLogger::LEVEL_WARNING);			
		}
		
		$countCheck =  count($this->blackList) +
							count($this->blackListFile) +
							count($this->blackListRegularExpression) +
							count($this->blackListRegularExpressionFile);							
		if (($countCheck) and (!$this->blackListVisited)) {
			Yii::log(Yii::t('yii','Blacklist defined but not used in "{class}".', array('{class}'=>__CLASS__)),CLogger::LEVEL_WARNING);			
		}

		$countCheck =  count($this->whiteList) +
							count($this->whiteListFile) +
							count($this->whiteListRegularExpression) +
							count($this->whiteListRegularExpressionFile);
		if (($countCheck) and (!$this->whiteListVisited)) {
			Yii::log(Yii::t('yii','Whitelist defined but not used in "{class}".', array('{class}'=>__CLASS__)),CLogger::LEVEL_WARNING);			
		}

		$countCheck = 	count($this->substitutionList) + 
							count($this->substitutionListFile) +
							count($this->substitutionListRegularExpression) +
							count($this->substitutionListRegularExpressionFile);
		if (($countCheck) and (!$this->substitutionListVisited)) {
			Yii::log(Yii::t('yii','Substitution List defined but not used in "{class}".', array('{class}'=>__CLASS__)),CLogger::LEVEL_WARNING);			
		}
	}

	/**
	 * Performs all functions necessary to import tags from all import sources as well as perform
	 * all subsequent modifications
	 * The result is that the array $this->frequencyList is created which is then used to display the tag cloud
	 * @toDo Locale
	 */
	public function generateList($locale = false) {
		$this->issueUsageWarnings();
		if ($this->removeNumeric) {
			$this->internalTagList = $this->removeNumericItems($this->internalTagList);
		}
		//count the occurances of each tag and create a unique array with count total
		//$wordCount = array_count_values($this->internalTagList);
		$this->tagFrequencyList = array_count_values($this->internalTagList);

		// sort the result (if necessary)
		if ($locale) {
			setlocale(LC_COLLATE, $locale);
		}
		if ($this->sortTagList > 0) {
			ksort($this->tagFrequencyList, SORT_LOCALE_STRING);
		} elseif ($this->sortTagList < 0) {
			krsort($this->tagFrequencyList);
		}
	}
}