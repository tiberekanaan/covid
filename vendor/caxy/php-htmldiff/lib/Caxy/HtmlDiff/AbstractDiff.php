<?php

namespace Caxy\HtmlDiff;

use Caxy\HtmlDiff\Util\MbStringUtil;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Class AbstractDiff.
 */
abstract class AbstractDiff
{
    /**
     * @var array
     *
     * @deprecated since 0.1.0
     */
    public static $defaultSpecialCaseTags = array('strong', 'b', 'i', 'big', 'small', 'u', 'sub', 'sup', 'strike', 's', 'p');

    /**
     * @var array
     *
     * @deprecated since 0.1.0
     */
    public static $defaultSpecialCaseChars = array('.', ',', '(', ')', '\'');

    /**
     * @var bool
     *
     * @deprecated since 0.1.0
     */
    public static $defaultGroupDiffs = true;

    /**
     * @var HtmlDiffConfig
     */
    protected $config;

    /**
     * @var string
     */
    protected $content;

    /**
     * @var string
     */
    protected $oldText;

    /**
     * @var string
     */
    protected $newText;

    /**
     * @var array
     */
    protected $oldWords = array();

    /**
     * @var array
     */
    protected $newWords = array();

    /**
     * @var DiffCache[]
     */
    protected $diffCaches = array();

    /**
     * @var HTMLPurifier|null
     */
    protected $purifier;

    /**
     * @var HTMLPurifier_Config|null
     */
    protected $purifierConfig = null;

    /**
     * @see array_slice_cached();
     * @var bool
     */
    protected $resetCache = false;

    /**
     * @var MbStringUtil
     */
    protected $stringUtil;

    /**
     * AbstractDiff constructor.
     *
     * @param string     $oldText
     * @param string     $newText
     * @param string     $encoding
     * @param null|array $specialCaseTags
     * @param null|bool  $groupDiffs
     */
    public function __construct($oldText, $newText, $encoding = 'UTF-8', $specialCaseTags = null, $groupDiffs = null)
    {
        $this->stringUtil = new MbStringUtil($oldText, $newText);

        $this->setConfig(HtmlDiffConfig::create()->setEncoding($encoding));

        if ($specialCaseTags !== null) {
            $this->config->setSpecialCaseTags($specialCaseTags);
        }

        if ($groupDiffs !== null) {
            $this->config->setGroupDiffs($groupDiffs);
        }

        $this->oldText = $oldText;
        $this->newText = $newText;
        $this->content = '';
    }

    /**
     * @return bool|string
     */
    abstract public function build();

    /**
     * Initializes HTMLPurifier with cache location.
     *
     * @param null|string $defaultPurifierSerializerCache
     */
    public function initPurifier($defaultPurifierSerializerCache = null)
    {
        if (null !== $this->purifierConfig) {
            $HTMLPurifierConfig  = $this->purifierConfig;
        } else {
            $HTMLPurifierConfig = HTMLPurifier_Config::createDefault();
        }

        // Cache.SerializerPath defaults to Null and sets
        // the location to inside the vendor HTMLPurifier library
        // under the DefinitionCache/Serializer folder.
        if (!is_null($defaultPurifierSerializerCache)) {
            $HTMLPurifierConfig->set('Cache.SerializerPath', $defaultPurifierSerializerCache);
        }

        // Cache.SerializerPermissions defaults to 0744.
        // This setting allows the cache files to be deleted by any user, as they are typically
        // created by the web/php user (www-user, php-fpm, etc.)
        $HTMLPurifierConfig->set('Cache.SerializerPermissions', 0777);

        $this->purifier = new HTMLPurifier($HTMLPurifierConfig);
    }

    /**
     * Prepare (purify) the HTML
     *
     * @return void
     */
    protected function prepare()
    {
        if (false === $this->config->isPurifierEnabled()) {
            return;
        }

        $this->initPurifier($this->config->getPurifierCacheLocation());

        $this->oldText = $this->purifyHtml($this->oldText);
        $this->newText = $this->purifyHtml($this->newText);
    }

    /**
     * @return DiffCache|null
     */
    protected function getDiffCache()
    {
        if (!$this->hasDiffCache()) {
            return null;
        }

        $hash = spl_object_hash($this->getConfig()->getCacheProvider());

        if (!array_key_exists($hash, $this->diffCaches)) {
            $this->diffCaches[$hash] = new DiffCache($this->getConfig()->getCacheProvider());
        }

        return $this->diffCaches[$hash];
    }

    /**
     * @return bool
     */
    protected function hasDiffCache()
    {
        return null !== $this->getConfig()->getCacheProvider();
    }

    /**
     * @return HtmlDiffConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param HtmlDiffConfig $config
     *
     * @return AbstractDiff
     */
    public function setConfig(HtmlDiffConfig $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return int
     *
     * @deprecated since 0.1.0
     */
    public function getMatchThreshold()
    {
        return $this->config->getMatchThreshold();
    }

    /**
     * @param int $matchThreshold
     *
     * @return AbstractDiff
     *
     * @deprecated since 0.1.0
     */
    public function setMatchThreshold($matchThreshold)
    {
        $this->config->setMatchThreshold($matchThreshold);

        return $this;
    }

    /**
     * @param array $chars
     *
     * @deprecated since 0.1.0
     */
    public function setSpecialCaseChars(array $chars)
    {
        $this->config->setSpecialCaseChars($chars);
    }

    /**
     * @return array|null
     *
     * @deprecated since 0.1.0
     */
    public function getSpecialCaseChars()
    {
        return $this->config->getSpecialCaseChars();
    }

    /**
     * @param string $char
     *
     * @deprecated since 0.1.0
     */
    public function addSpecialCaseChar($char)
    {
        $this->config->addSpecialCaseChar($char);
    }

    /**
     * @param string $char
     *
     * @deprecated since 0.1.0
     */
    public function removeSpecialCaseChar($char)
    {
        $this->config->removeSpecialCaseChar($char);
    }

    /**
     * @param array $tags
     *
     * @deprecated since 0.1.0
     */
    public function setSpecialCaseTags(array $tags = array())
    {
        $this->config->setSpecialCaseChars($tags);
    }

    /**
     * @param string $tag
     *
     * @deprecated since 0.1.0
     */
    public function addSpecialCaseTag($tag)
    {
        $this->config->addSpecialCaseTag($tag);
    }

    /**
     * @param string $tag
     *
     * @deprecated since 0.1.0
     */
    public function removeSpecialCaseTag($tag)
    {
        $this->config->removeSpecialCaseTag($tag);
    }

    /**
     * @return array|null
     *
     * @deprecated since 0.1.0
     */
    public function getSpecialCaseTags()
    {
        return $this->config->getSpecialCaseTags();
    }

    /**
     * @return string
     */
    public function getOldHtml()
    {
        return $this->oldText;
    }

    /**
     * @return string
     */
    public function getNewHtml()
    {
        return $this->newText;
    }

    /**
     * @return string
     */
    public function getDifference()
    {
        return $this->content;
    }

    /**
     * Clears the diff content.
     *
     * @return void
     */
    public function clearContent()
    {
        $this->content = null;
    }

    /**
     * @param bool $boolean
     *
     * @return $this
     *
     * @deprecated since 0.1.0
     */
    public function setGroupDiffs($boolean)
    {
        $this->config->setGroupDiffs($boolean);

        return $this;
    }

    /**
     * @return bool
     *
     * @deprecated since 0.1.0
     */
    public function isGroupDiffs()
    {
        return $this->config->isGroupDiffs();
    }

    /**
     * @param HTMLPurifier_Config $config
     */
    public function setHTMLPurifierConfig(HTMLPurifier_Config $config)
    {
        $this->purifierConfig = $config;
    }

    /**
     * @param string $html
     *
     * @return string
     */
    protected function purifyHtml($html)
    {
        if (null === $this->purifier) {
            return $html;
        }

        return $this->purifier->purify($html);
    }

    protected function splitInputsToWords()
    {
        $this->setOldWords($this->convertHtmlToListOfWords($this->explode($this->oldText)));
        $this->setNewWords($this->convertHtmlToListOfWords($this->explode($this->newText)));
    }

    /**
     * @param array $oldWords
     */
    protected function setOldWords(array $oldWords)
    {
        $this->resetCache = true;
        $this->oldWords   = $oldWords;
    }

    /**
     * @param array $newWords
     */
    protected function setNewWords(array $newWords)
    {
        $this->resetCache = true;
        $this->newWords   = $newWords;
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    protected function isPartOfWord($text)
    {
        return $this->ctypeAlphanumUnicode(str_replace($this->config->getSpecialCaseChars(), '', $text));
    }

    /**
     * @param array $characterString
     *
     * @return array
     */
    protected function convertHtmlToListOfWords($characterString)
    {
        $mode = 'character';
        $current_word = '';
        $words = array();
        $keepNewLines = $this->getConfig()->isKeepNewLines();
        foreach ($characterString as $i => $character) {
            switch ($mode) {
                case 'character':
                if ($this->isStartOfTag($character)) {
                    if ($current_word != '') {
                        $words[] = $current_word;
                    }

                    $current_word = '<';
                    $mode = 'tag';
                } elseif (preg_match("/\s/u", $character)) {
                    if ($current_word !== '') {
                        $words[] = $current_word;
                    }
                    $current_word = $keepNewLines ? $character : preg_replace('/\s+/Su', ' ', $character);
                    $mode = 'whitespace';
                } else {
                    if (
                        (($this->ctypeAlphanumUnicode($character) === true) && ($this->stringUtil->strlen($current_word) === 0 || $this->isPartOfWord($current_word))) ||
                        (in_array($character, $this->config->getSpecialCaseChars()) && isset($characterString[$i + 1]) && $this->isPartOfWord($characterString[$i + 1]))
                    ) {
                        $current_word .= $character;
                    } else {
                        $words[] = $current_word;
                        $current_word = $character;
                    }
                }
                break;
                case 'tag' :
                if ($this->isEndOfTag($character)) {
                    $current_word .= '>';
                    $words[] = $current_word;
                    $current_word = '';

                    if (!preg_match('[^\s]u', $character)) {
                        $mode = 'whitespace';
                    } else {
                        $mode = 'character';
                    }
                } else {
                    $current_word .= $character;
                }
                break;
                case 'whitespace':
                if ($this->isStartOfTag($character)) {
                    if ($current_word !== '') {
                        $words[] = $current_word;
                    }
                    $current_word = '<';
                    $mode = 'tag';
                } elseif (preg_match("/\s/u", $character)) {
                    $current_word .= $character;
                    if (!$keepNewLines) $current_word = preg_replace('/\s+/Su', ' ', $current_word);
                } else {
                    if ($current_word != '') {
                        $words[] = $current_word;
                    }
                    $current_word = $character;
                    $mode = 'character';
                }
                break;
                default:
                break;
            }
        }
        if ($current_word != '') {
            $words[] = $current_word;
        }

        return $words;
    }

    /**
     * @param string $val
     *
     * @return bool
     */
    protected function isStartOfTag($val)
    {
        return $val === '<';
    }

    /**
     * @param string $val
     *
     * @return bool
     */
    protected function isEndOfTag($val)
    {
        return $val === '>';
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function isWhiteSpace($value)
    {
        return !preg_match('[^\s]u', $value);
    }

    /**
     * @param string $value
     *
     * @return array
     */
    protected function explode($value)
    {
        // as suggested by @onassar
        return preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param string $str
     *
     * @return bool
     */
    protected function ctypeAlphanumUnicode($str)
    {
        return preg_match("/^[a-zA-Z0-9\pL]+$/u", $str) === 1;
    }
}
