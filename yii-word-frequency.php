<?php
/**
 * @author Andrew Potter <apc@andypotter.org>
 * 
 *	This class was written using the class @reference YiiTagCloud from Evandro Sviercowski <evandro.swk@gmail.com>
 *	Instead of extending the original class a new class was written using YiiTagCloud as a basis
 *
 *	The modifications made provide the ability of accumulating the list of words/tags that will appear in the 
 *	tag cloud. This includes public attributes and methodswhich allow one to internally create 
 *	the array &this->arrTags which is then displayed as a tag cloud just as it was in YiiTagCloud.
 *
 *	The additional possibillities include three types of tag sources and several modifications to the content of the list
 *	Sources can be strings, arrays of strings and active record query results,
 *	Additional possibilites for modifying the input sources and resulting list include:
 *		- specification of the separation delimiter for separating strings
 *		- a blacklist of words that should not be included in the final tag list
 *		- blacklists can be used which are stored in files along with the assets of this extension
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
 *				'maxFontSize' => 60,
 *				'htmlOptions' => array('style'=>'width: 900px; margin-left: auto; margin-right: auto'),
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
class APCTagCloud extends CWidget
{
    /**
     * @var string The APCTagCloud container css class
     */
    public $containerClass = 'APCTagCloud';
    /**
     * @var string The APCTagCloud container html tag
     */
    public $containerTag = 'div';
    /**
     * @var array HtmlOptions of the APCTagCloud container
     */
    public $htmlOptions = array();
    /**
     * @var array with the tags
     */
    protected $arrTags = array();
    /**
     * @var array words/tags which will make up the tag cloud
     */
    protected $internalTagList = array();
    /**
     * @var string delimiter used for converting(with the explode function) strings to words/tags which will make up the tag cloud
     */
    public $explosionDelimiter = ' ';
    /**
     * @var array of values/arrays containing words/tags to be prevented from being listed in the tag cloud
     * 	The multidimensional array allows one to maintain separate blacklists and combine them for used
     *	in screening tags from the cloud. For instance blacklists could be maintained for words from
     *	different languages, different technical fields etc. The array depth can be arbitrarily deep as it is 
     *	processed internally with array_walk_recursive to retrieve all values present.
     */
    public $blackList = array();
    /**
     * @var array of filenames contining blacklist sets
     * see @link $blackList for explanation of blacklists
     * the files should contain a one-dimensional list of blacklist terms (i.e.one word per line)
     * the files should be located in the assets directory of this extension
     */
    public $blackListFile = array();
    /**
     * @var array of key value search replace strings. Useful for eliminating punction marks from text
     */
    public $substitutionList = array();
    /**
     * @var string, contains a string which will be parsed and each individual word will be added to the tag cloud list
     */
    public $stringSource = '';
    /**
     * @var array, contains an array of arrays, which contain strings of text. Each string will be parsed and each individual word will be added to the tag cloud list
     */
    public $arraySourceList = array();
    /**
     * @var array, contains an array of arrays which describe Active Record Queries and field list for active records, each string from the resulting queries will be parsed for individual words which will be added to the tag cloud list
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
     * @var string The begin color of the tags
     */
    public $beginColor = '000842';
    /**
     * @var string The end color of the tags
     */
    public $endColor = 'A3AEFF';
    /**
     * @var integer The smallest count (or occurrence).
     */
    public $minWeight = 1;
    /**
     * @var integer The largest count (or occurrence).
     */ 
    public $maxWeight = 1;
    /**
     * @var array the font-size colors
     */
    public $arrFontColor = array();    
    /**
     * @var integer The smallest font-size.
     */ 
    public $minFontSize = 8;
    /**
     * @var integer The largest font-size.
     */ 
    public $maxFontSize = 36;
    /**
     * @var string the URL of the CSS file
     */
	 public $cssFile;
    /**
     * @var string the URL for this extensions assets directory
     */
	 protected $extensionAssetUrl;

	/**
	 * @note changed in APCTagCloud, see method run()
	 */	
	public function init()	{
		$this->htmlOptions['id']=$this->getId();

		if(!isset($this->htmlOptions['class']))
			$this->htmlOptions['class'] = $this->containerClass;
		
		$this->extensionAssetUrl = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'assets';
		$baseScriptUrl = Yii::app()->getAssetManager()->publish(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'assets' );

		if($this->cssFile !== false) {
			if($this->cssFile === null)
			$this->cssFile = $baseScriptUrl . '/APCTagCloud.css';
			Yii::app()->getClientScript()->registerCssFile($this->cssFile);
		}
	}

	// =======================================
	// TAG Accumulation functions
	// ---------------------------------------
	/**
	 * Adds words from $inputString into the class list of words/tags ($this->internalTagList) which is an array of individual words
	 * this is the only class method where elements are added to $this->internalTagList
	 * @param string $inputString list of words to be added as tags to $this->internalTagList
	 * @note new in APCTagCloud
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
	 * @note new in APCTagCloud
	 */
	protected function accumulateTagsfromArrays($arraySource) {
		array_walk_recursive($arraySource, array($this, "addStringToTagList"));
	}
	
	/**
	 * Adds words from a list of Active Records into the class list of words/tags ($this->internalTagList) which is an array of individual words
	 * $this->activeRecordSourceList is an array contining a list of arrays which have the two keys:
	 *		model: is the active record (e.g. the value returned by the findAll() function) 
	 *		attribute: another array with a list of attribute names from the active record, whose text values should be imported
	 * @note new in APCTagCloud
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
	 * @note new in APCTagCloud
	 */
	protected function accumulateTagsfromSources() {
		$this->addStringToTagList($this->stringSource);
		$this->accumulateTagsfromArrays($this->arraySourceList);
		$this->accumulateTagsfromActiveRecords();
	}

	/**
	 * removes all items from $inputList which are in the $this->blackList list of arrays
	 * the comparison is case insensitive
	 * @param array $inputLst, list of words/tabs which are to compared and removed if found in the $this->blackList arrays
	 * @return array $inputList stripped of items found in the $this->blackList arrays
	 * @note new in APCTagCloud
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
	 * @note new in APCTagCloud
	 */
	protected function removeNumericItems($inputList) {
		return array_filter($inputList, function($arg) { return intval($arg) == 0; });
	}
    
	/**
	 * Performs all functions necessary to import word/tags from all import sources as well as perform
	 * all subsequent modifications
	 * The result os that the array $this->arrTags is created which is then used to display the tag cloud
	 * @note new in APCTagCloud
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
	 * @note changed in APCTagCloud
	 */	
	public function run() {
		$this->generateTagArray(); //new from APCTagCloud
		$this->getMinAndMaxWeight(); //originally in init() in YiiTagCloud
		$this->getFontSizes(); //originally in init() in YiiTagCloud
		$this->genereteColors(); //originally in init() in YiiTagCloud
		$this->renderTagCloud();
	}

	/**
	 * @note original method from YiiTagCloud
	 */	
	protected function renderTagCloud() {
		echo CHtml::openTag($this->containerTag, $this->htmlOptions);
		foreach ($this->arrTags as $tag => $conf) {
			$url = isset($conf['url']) ? $conf['url'] : 'javascript:return false';
			$htmlOptions = isset($conf['htmlOptions']) ? $conf['htmlOptions'] : array();

			if (!isset($htmlOptions['style']) || empty($htmlOptions['style'])) {
				$htmlOptions['style'] = 'font-size: ' .$conf['font-size'] . 'pt;' . 'color: ' . $this->arrFontColor[$conf['font-size']];
			}

			if (!isset($htmlOptions['target']) || empty($htmlOptions['target'])) {
				$htmlOptions['target'] = '_blank';
			}

			@$htmlOptions['class'] .= ' APCTagCloudWord';

			echo ' &nbsp;' . CHtml::link($tag, $url, $htmlOptions) . '&nbsp; ';
		}
		echo CHtml::closeTag($this->containerTag);
	}

	/**
	 * @note original method from YiiTagCloud
	 */	
    protected function getMinAndMaxWeight() {

        foreach ($this->arrTags as $conf) {        
            if ($this->minWeight > $conf['weight'])
                $this->minWeight = $conf['weight'];

            if ($this->maxWeight < $conf['weight'])
                $this->maxWeight = $conf['weight'];
        }
    }
    
	/**
	 * @note original method from YiiTagCloud
	 */	
    protected function getFontSizes() {
        foreach ($this->arrTags as &$conf) {           
            $conf['font-size'] = $this->calcFontSize($conf['weight']);
            $this->arrFontColor[$conf['font-size']] = ''; 
        }
    }

	/**
	 * @note original method from YiiTagCloud
	 */	
    protected function calcFontSize($weight) {
        //Just a precaution, it shouldn't happen if all weights are > 0
        if ($this->maxWeight - $this->minWeight == 0)
            return round(($weight - $this->minWeight) + $this->minFontSize);

    return round(((($weight - $this->minWeight) * ($this->maxFontSize - $this->minFontSize)) / ($this->maxWeight - $this->minWeight)) + $this->minFontSize);
    }

	/**
	 * @note original method from YiiTagCloud
	 */	
    protected function genereteColors () {
        krsort ($this->arrFontColor);
        $beginColor = hexdec($this->beginColor);
        $endColor = hexdec($this->endColor);

        $R0 = ($beginColor & 0xff0000) >> 16;
        $G0 = ($beginColor & 0x00ff00) >> 8;
        $B0 = ($beginColor & 0x0000ff) >> 0;

        $R1 = ($endColor & 0xff0000) >> 16;
        $G1 = ($endColor & 0x00ff00) >> 8;
        $B1 = ($endColor & 0x0000ff) >> 0;

        $numColors = count($this->arrFontColor);
        
        $i =0;
        foreach ($this->arrFontColor as &$value) {
            $R = $this->interpolate($R0, $R1, $i, $numColors);
            $G = $this->interpolate($G0, $G1, $i, $numColors);
            $B = $this->interpolate($B0, $B1, $i, $numColors);

            $value = sprintf("#%06X",(((($R << 8) | $G) << 8) | $B));

            $i++;
        }

    }

	/**
	 * @note original method from YiiTagCloud
	 */	
    protected function interpolate($pBegin, $pEnd, $pStep, $pMax) {
        if ($pBegin < $pEnd) 
            return (($pEnd - $pBegin) * ($pStep / $pMax)) + $pBegin;

        return (($pBegin - $pEnd) * (1 - ($pStep / $pMax))) + $pEnd;
    }
}
