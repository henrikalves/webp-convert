<?php

namespace WebPConvert\Convert\Converters\BaseTraits;

use WebPConvert\Convert\Converters\Stack;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConversionSkippedException;
use WebPConvert\Options\Exceptions\InvalidOptionValueException;
use WebPConvert\Options\Exceptions\InvalidOptionTypeException;

use WebPConvert\Options\ArrayOption;
use WebPConvert\Options\BooleanOption;
use WebPConvert\Options\GhostOption;
use WebPConvert\Options\IntegerOption;
use WebPConvert\Options\IntegerOrNullOption;
use WebPConvert\Options\MetadataOption;
use WebPConvert\Options\Options;
use WebPConvert\Options\StringOption;
use WebPConvert\Options\QualityOption;
use WebPConvert\Options\OptionFactory;

/**
 * Trait for handling options
 *
 * This trait is currently only used in the AbstractConverter class. It has been extracted into a
 * trait in order to bundle the methods concerning options.
 *
 * @package    WebPConvert
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since Release 2.0.0
 */
trait OptionsTrait
{

    abstract public function log($msg, $style = '');
    abstract public function logLn($msg, $style = '');
    abstract protected function getMimeTypeOfSource();

    /** @var array  Provided conversion options (array of simple objects)*/
    public $providedOptions;

    /** @var array  Calculated conversion options (merge of default options and provided options)*/
    protected $options;

    /** @var Options  */
    protected $options2;

    /**
     *  Get the "general" options (options that are standard in the meaning that they
     *  are generally available (unless specifically marked as unsupported by a given converter)
     *
     *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
     *
     *  @return  array  Array of options
     */
    public function getGeneralOptions($imageType)
    {
        $isPng = ($imageType == 'png');

        $defaultQualityOption = new IntegerOption('default-quality', ($isPng ? 85 : 75), 0, 100);
        $defaultQualityOption->markDeprecated();

        $maxQualityOption = new IntegerOption('max-quality', 85, 0, 100);
        $maxQualityOption->markDeprecated();

        return OptionFactory::createOptions([
            ['encoding', 'string', ['default' => 'auto', 'allowedValues' => ['lossy', 'lossless', 'auto']]],
            ['quality', 'int', ['default' => ($isPng ? 85 : 75), 'min' => 0, 'max' => 100]],
            ['auto-limit', 'boolean', ['default' => true]],
            ['near-lossless', 'int', ['default' => 60, 'min' => 0, 'max' => 100]],
            ['alpha-quality', 'int', ['default' => 85, 'min' => 0, 'max' => 100]],
            ['metadata', 'string', ['default' => 'none']],
            ['method', 'int', ['default' => 6, 'min' => 0, 'max' => 6]],
            ['sharp-yuv', 'boolean', ['default' => true]],
            ['auto-filter', 'boolean', ['default' => false]],
            ['low-memory', 'boolean', ['default' => false]],
            ['preset', 'string', [
                'default' => 'none',
                'allowedValues' => ['none', 'default', 'photo', 'picture', 'drawing', 'icon', 'text']]
            ],
            ['size-in-percentage', 'int', ['default' => null, 'min' => 0, 'max' => 100, 'allow-null' => true]],
            ['skip', 'boolean', ['default' => false]],
            ['log-call-arguments', 'boolean', ['default' => false]],
            ['default-quality', 'int', [
                'default' => ($isPng ? 85 : 75),
                'min' => 0,
                'max' => 100,
                'deprecated' => true]
            ],
            ['max-quality', 'int', ['default' => 85, 'min' => 0, 'max' => 100, 'deprecated' => true]],
            // TODO: use-nice should not be a "general" option
            ['use-nice', 'boolean', ['default' => false]],
            ['jpeg', 'array', ['default' => []]],
            ['png', 'array', ['default' => []]],
        ]);
/*
        return [
            new IntegerOption('alpha-quality', 85, 0, 100),
            new BooleanOption('auto-limit', true),
            //new IntegerOption('auto-limit-adjustment', 5, -100, 100),
            new BooleanOption('auto-filter', false),
            $defaultQualityOption,
            new StringOption('encoding', 'auto', ['lossy', 'lossless', 'auto']),
            new BooleanOption('low-memory', false),
            new BooleanOption('log-call-arguments', false),
            $maxQualityOption,
            new MetadataOption('metadata', 'none'),
            new IntegerOption('method', 6, 0, 6),
            new IntegerOption('near-lossless', 60, 0, 100),
            new StringOption('preset', 'none', ['none', 'default', 'photo', 'picture', 'drawing', 'icon', 'text']),
            new QualityOption('quality', ($isPng ? 85 : 75)),
            new IntegerOrNullOption('size-in-percentage', null, 0, 100),
            new BooleanOption('sharp-yuv', true),
            new BooleanOption('skip', false),
            new BooleanOption('use-nice', false),
            new ArrayOption('jpeg', []),
            new ArrayOption('png', [])
        ];*/
    }

    /**
     *  Get ui definitions for the unique options of this converter
     *
     *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
     *
     *  @return  array  Hash of objects indexed by option id
     */
    public function getUIForGeneralOptions($imageType)
    {
        return [
            'alpha-quality' => [
                'type' => 'input',
                'label' => 'Alpha quality',
                'help-text' => 'Quality of alpha channel. ' .
                    'Only relevant for lossy encoding and only relevant for images' .
                    'with transparency',
                "display-condition" => [
                    'type' => 'not-equals',
                    'arg1' => [
                        'type' => 'option-value',
                        'option-id' => 'encoding'
                    ],
                    'arg2' => 'lossy'
                ],
            ],
            'encoding' => [
                'type' => 'select',
                'label' => 'Encoding',
                'options' => ['auto', 'lossy', 'lossless'],
                'optionLabels' => [
                    'auto' => 'Auto',
                    'lossy' => 'Lossy',
                    'lossless' => 'Lossless'
                ],
                'help-text' => 'Set encoding for the webp. ' .
                    'If you choose "auto", webp-convert will ' .
                    'convert to both lossy and lossless and pick the smallest result',
            ],
        ];
    }

    /**
     *  Get the unique options for a converter
     *
     *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
     *
     *  @return  array  Array of options
     */
    public function getUniqueOptions($imageType)
    {
        return [];
    }


    /**
     *  Create options.
     *
     *  The options created here will be available to all converters.
     *  Individual converters may add options by overriding this method.
     *
     *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
     *
     *  @return void
     */
    protected function createOptions($imageType = 'png')
    {
        $this->options2 = new Options();
        $this->options2->addOptions(... $this->getGeneralOptions($imageType));
        $this->options2->addOptions(... $this->getUniqueOptions($imageType));
    }

    /**
     * Set "provided options" (options provided by the user when calling convert().
     *
     * This also calculates the protected options array, by merging in the default options, merging
     * jpeg and png options and merging prefixed options (such as 'vips-quality').
     * The resulting options array are set in the protected property $this->options and can be
     * retrieved using the public ::getOptions() function.
     *
     * @param   array $providedOptions (optional)
     * @return  void
     */
    public function setProvidedOptions($providedOptions = [])
    {
        $imageType = ($this->getMimeTypeOfSource() == 'image/png' ? 'png' : 'jpeg');
        $this->createOptions($imageType);

        $this->providedOptions = $providedOptions;

        if (isset($this->providedOptions['png'])) {
            if ($this->getMimeTypeOfSource() == 'image/png') {
                $this->providedOptions = array_merge($this->providedOptions, $this->providedOptions['png']);
//                $this->logLn(print_r($this->providedOptions, true));
                unset($this->providedOptions['png']);
            }
        }

        if (isset($this->providedOptions['jpeg'])) {
            if ($this->getMimeTypeOfSource() == 'image/jpeg') {
                $this->providedOptions = array_merge($this->providedOptions, $this->providedOptions['jpeg']);
                unset($this->providedOptions['jpeg']);
            }
        }

        // merge down converter-prefixed options
        $converterId = self::getConverterId();
        $strLen = strlen($converterId);
        foreach ($this->providedOptions as $optionKey => $optionValue) {
            if (substr($optionKey, 0, $strLen + 1) == ($converterId . '-')) {
                $this->providedOptions[substr($optionKey, $strLen + 1)] = $optionValue;
            }
        }

        // Create options (Option objects)
        foreach ($this->providedOptions as $optionId => $optionValue) {
            $this->options2->setOrCreateOption($optionId, $optionValue);
        }
        //$this->logLn(print_r($this->options2->getOptions(), true));
//$this->logLn($this->options2->getOption('hello'));

        // Create flat associative array of options
        $this->options = $this->options2->getOptions();

        // -  Merge $defaultOptions into provided options
        //$this->options = array_merge($this->getDefaultOptions(), $this->providedOptions);

        //$this->logOptions();
    }

    /**
     * Get the resulting options after merging provided options with default options.
     *
     * Note that the defaults depends on the mime type of the source. For example, the default value for quality
     * is "auto" for jpegs, and 85 for pngs.
     *
     * @return array  An associative array of options: ['metadata' => 'none', ...]
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Change an option specifically.
     *
     * This method is probably rarely neeeded. We are using it to change the "encoding" option temporarily
     * in the EncodingAutoTrait.
     *
     * @param  string  $id      Id of option (ie "metadata")
     * @param  mixed   $value   The new value.
     * @return void
     */
    protected function setOption($id, $value)
    {
        $this->options[$id] = $value;
        $this->options2->setOrCreateOption($id, $value);
    }

    /**
     *  Check options.
     *
     *  @throws InvalidOptionTypeException   if an option have wrong type
     *  @throws InvalidOptionValueException  if an option value is out of range
     *  @throws ConversionSkippedException   if 'skip' option is set to true
     *  @return void
     */
    protected function checkOptions()
    {
        $this->options2->check();

        if ($this->options['skip']) {
            if (($this->getMimeTypeOfSource() == 'image/png') && isset($this->options['png']['skip'])) {
                throw new ConversionSkippedException(
                    'skipped conversion (configured to do so for PNG)'
                );
            } else {
                throw new ConversionSkippedException(
                    'skipped conversion (configured to do so)'
                );
            }
        }
    }

    public function logOptions()
    {
        $this->logLn('');
        $this->logLn('Options:');
        $this->logLn('------------');
        $this->logLn(
            'The following options have been set explicitly. ' .
            'Note: it is the resulting options after merging down the "jpeg" and "png" options and any ' .
            'converter-prefixed options.'
        );
        $this->logLn('- source: ' . $this->source);
        $this->logLn('- destination: ' . $this->destination);

        $unsupported = $this->getUnsupportedDefaultOptions();
        //$this->logLn('Unsupported:' . print_r($this->getUnsupportedDefaultOptions(), true));
        $ignored = [];
        $implicit = [];
        foreach ($this->options2->getOptionsMap() as $id => $option) {
            if (($id == 'png') || ($id == 'jpeg')) {
                continue;
            }
            if ($option->isValueExplicitlySet()) {
                if (($option instanceof GhostOption) || in_array($id, $unsupported)) {
                    //$this->log(' (note: this option is ignored by this converter)');
                    if (($id != '_skip_input_check') && ($id != '_suppress_success_message')) {
                        $ignored[] = $option;
                    }
                } else {
                    $this->log('- ' . $id . ': ');
                    $this->log($option->getValueForPrint());
                    $this->logLn('');
                }
            } else {
                if (($option instanceof GhostOption) || in_array($id, $unsupported)) {
                } else {
                    $implicit[] = $option;
                }
            }
        }

        if (count($implicit) > 0) {
            $this->logLn('');
            $this->logLn(
                'The following options have not been explicitly set, so using the following defaults:'
            );
            foreach ($implicit as $option) {
                $this->log('- ' . $option->getId() . ': ');
                $this->log($option->getValueForPrint());
                $this->logLn('');
            }
        }
        if (count($ignored) > 0) {
            $this->logLn('');
            if ($this instanceof Stack) {
                $this->logLn(
                    'The following options were supplied and are passed on to the converters in the stack:'
                );
                foreach ($ignored as $option) {
                    $this->log('- ' . $option->getId() . ': ');
                    $this->log($option->getValueForPrint());
                    $this->logLn('');
                }
            } else {
                $this->logLn(
                    'The following options were supplied but are ignored because they are not supported by this ' .
                        'converter:'
                );
                foreach ($ignored as $option) {
                    $this->logLn('- ' . $option->getId());
                }
            }
        }
        $this->logLn('------------');
    }

    // to be overridden by converters
    protected function getUnsupportedDefaultOptions()
    {
        return [];
    }

    /**
        *  Get unique option definitions.
        *
        *  Gets definitions of the converters "unique" options (that is, those options that
        *  are not general). It was added in order to give GUI's a way to automatically adjust
        *  their setting screens.
        *
        *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
        *
        *  @return  array  Array of options definitions - ready to be json encoded, or whatever
        */
    public function getUniqueOptionDefinitions($imageType = 'png')
    {
        $uniqueOptions = new Options();
        $uniqueOptions->addOptions(... $this->getUniqueOptions($imageType));
        return $uniqueOptions->getDefinitions();
    }

    public function getGeneralOptionDefinitions($imageType = 'png')
    {
        $generalOptions = new Options();
        $generalOptions->addOptions(... $this->getGeneralOptions($imageType));
        return $generalOptions->getDefinitions();
    }

    public function getSupportedGeneralOptions($imageType = 'png')
    {
        $unsupportedGeneral = $this->getUnsupportedDefaultOptions();
        $generalOptionsArr = $this->getGeneralOptions($imageType);
        $supportedIds = [];
        foreach ($generalOptionsArr as $i => $option) {
            if (in_array($option->getId(), $unsupportedGeneral)) {
                unset($generalOptionsArr[$i]);
            }
        }
        return $generalOptionsArr;
    }

       /**
        *  Get general option definitions.
        *
        *  Gets definitions of the converters "general" options. (that is, those options that
        *  It was added in order to give GUI's a way to automatically adjust their setting screens.
        *
        *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
        *
        *  @return  array  Array of options definitions - ready to be json encoded, or whatever
        */
    public function getSupportedGeneralOptionDefinitions($imageType = 'png')
    {
        $generalOptions = new Options();
        $generalOptions->addOptions(... $this->getSupportedGeneralOptions($imageType));
        return $generalOptions->getDefinitions();
    }

    public function getSupportedGeneralOptionIds()
    {
        $supportedGeneralOptions = $this->getSupportedGeneralOptions();
        $supportedGeneralIds = [];
        foreach ($supportedGeneralOptions as $option) {
            $supportedGeneralIds[] = $option->getId();
        }
        return $supportedGeneralIds;
    }

       /**
        *  Get option definitions.
        *
        *  Added in order to give GUI's a way to automatically adjust their setting screens.
        *
        *  @param   string   $imageType   (png | jpeg)   The image type - determines the defaults
        *  @param   bool     $returnGeneral              Whether the general setting definitions should be returned
        *  @param   bool     $returnGeneralSupport       Whether the ids of supported/unsupported general options
        *                                                should be returned
        *
        *  @return  array  Array of options definitions - ready to be json encoded, or whatever
        */
    public function getOptionDefinitions($imageType = 'png', $returnGeneral = true, $returnGeneralSupport = true)
    {
        $result = [
            'unique' => $this->getUniqueOptionDefinitions($imageType),
        ];
        if ($returnGeneral) {
            $result['general'] = $this->getSupportedGeneralOptionDefinitions($imageType);
        }
        if ($returnGeneralSupport) {
            $result['supported-general'] = $this->getSupportedGeneralOptionIds();
            $result['unsupported-general'] = $this->getUnsupportedDefaultOptions();
        }
        return $result;
    }
/*
    public static function getUniqueOptions($imageType = 'png')
    {
        $options = new Options();
//        $options->addOptions(... self::getGeneralOptions($imageType));
//        $options->addOptions(... self::getUniqueOptions($imageType));

        return $options->getDefinitions();
    }*/
}
