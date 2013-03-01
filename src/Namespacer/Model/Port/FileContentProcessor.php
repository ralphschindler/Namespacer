<?php

class FileContentProcessor
{
    protected $_fileNameProcessor = null;
    protected $_prefixes = array();
    protected $_fileRegistry = null;
    protected $_canTokenizeDocblocks = false;

    protected $_libraryDirectory = null;
    protected $_relativeFilePath = null;

    protected $_originalContent = null;
    protected $_originalTokens = null;

    protected $_interestingInformation = array();
    protected $_interestingTokens = array();
    protected $_interestingTokenIndex = array();

    protected $_newTokens = array();

    public function __construct(FileNameProcessor $fileNameProcessor, $prefixes = array(), FileRegistry $fileRegistry = null)
    {
        $this->_fileNameProcessor = $fileNameProcessor;
        $this->_prefixes = $prefixes;
        $this->_fileRegistry = $fileRegistry;

        if (extension_loaded('docblock')) {
            $this->_canTokenizeDocblocks = true;
        }

        $this->_process();
    }

    public function getFileNameProcessor()
    {
        return $this->_fileNameProcessor;
    }

    public function getInformation()
    {
        $info = array();
        foreach ($this->_interestingTokens as $tokenType => $tokenInfo) {
            switch ($tokenType) {
                case 'className':
                    $info['className'] = $tokenInfo->value;
                    break;
                case 'consumedClass':
                    $info['consumedClasses'] = array();
                    foreach ((array) $tokenInfo as $consumedClassToken) {
                        if (!in_array($consumedClassToken->value, $info['consumedClasses'])) {
                            $info['consumedClasses'][] = $consumedClassToken->value;
                        }
                    }
                    break;
            }
        }
        return $info;
    }

    public function getNewContents()
    {
        if (!$this->_newTokens) {
            $this->convert();
        }

        $contents = null;
        foreach ($this->_newTokens as $token) {
            if (is_array($token)) {
                $contents .= $token[1];
            } else {
                $contents .= $token;
            }
        }

        return $contents;
    }

    protected function _process()
    {
        $this->_analyze();
        $this->_generate();
    }

    protected function _analyze()
    {
        $this->_originalContent = file_get_contents($this->_fileNameProcessor->getOriginalFilePath());
        $this->_originalTokens = token_get_all($this->_originalContent);
        $this->_staticAnalysis();
        // other analysis processes here?
    }

    protected function _staticAnalysis()
    {
        $context = null;

        foreach ($this->_originalTokens as $tokenNumber => $token) {
            if (is_array($token)) {
                $tokenName = token_name($token[0]);
            } else {
                $tokenName = 'string:' . $token;
            }

            if ($tokenNumber >= 3 && $context == 'INSIDE_OPEN_TAG') {
                $context = null;
            }

            // mostly for debugging
            $surroundingTokens = array();
            $surroundingTokens[-2] = (isset($this->_originalTokens[$tokenNumber - 2])) ? $this->_originalTokens[$tokenNumber - 2] : null;
            $surroundingTokens[-1] = (isset($this->_originalTokens[$tokenNumber - 1])) ? $this->_originalTokens[$tokenNumber - 1] : null;
            $surroundingTokens[1]  = (isset($this->_originalTokens[$tokenNumber + 1])) ? $this->_originalTokens[$tokenNumber + 1] : null;
            $surroundingTokens[2]  = (isset($this->_originalTokens[$tokenNumber + 2])) ? $this->_originalTokens[$tokenNumber + 2] : null;

            switch ($tokenName) {
                case 'T_OPEN_TAG':
                    if ($tokenNumber < 3) {
                        $context = 'INSIDE_OPEN_TAG';
                    }
                    break;
                case 'T_DOC_COMMENT':
                    if ($context == 'INSIDE_OPEN_TAG') {
                        $this->_registerInterestingToken('topOfFile', $tokenNumber + 1, true);
                        $context = null;
                    }
                    $this->_registerInterestingToken('docblock', $tokenNumber, true);
                    break;

                case 'T_INTERFACE':
                    $context = 'INSIDE_CLASS_DECLARATION';
                    $this->_interestingInformation['isInterface'] = true;
                case 'T_CLASS':
                    $context = 'INSIDE_CLASS_DECLARATION';
                    break;
                case 'T_ABSTRACT':
                    $this->_interestingInformation['isAbstract'] = true;
                    break;
                case 'T_EXTENDS':
                case 'T_IMPLEMENTS':
                    $context = 'INSIDE_CLASS_SIGNATURE';
                    break;
                case 'T_NEW':
                    $context = 'INSIDE_NEW_ASSIGNMENT';
                    break;
                case 'T_FUNCTION':
                    $context = 'INSIDE_FUNCTION_SIGNATURE_START';
                    break;
                case 'T_CATCH':
                    $context = 'INSIDE_CATCH_STATEMENT';
                    break;
                case 'string:{':
                    $context = null;
                    break;
                case 'string:(':
                    if ($context == 'INSIDE_FUNCTION_SIGNATURE_START') {
                        $context = 'INSIDE_FUNCTION_SIGNATURE';
                    }
                    break;
                case 'string:)':
                    if ($context == 'INSIDE_FUNCTION_SIGNATURE') {
                        $context = null;
                    }
                    break;
                case 'T_DOUBLE_COLON':
                    if (!in_array($this->_originalTokens[$tokenNumber-1][1], array('self', 'parent', 'static'))) {
                        $this->_registerInterestingToken('consumedClass', $tokenNumber - 1);
                    }
                    break;
                case 'T_INSTANCEOF':
                    if (!in_array($this->_originalTokens[$tokenNumber+2][1], array('self', 'parent', 'static'))) {
                        $this->_registerInterestingToken('consumedClass', $tokenNumber + 2);
                    }

                case 'T_STRING':
                    switch ($context) {
                        case 'INSIDE_CLASS_DECLARATION':
                            $this->_registerInterestingToken('className', $tokenNumber, true);
                            $context = null;
                            break;
                        case 'INSIDE_CLASS_SIGNATURE':
                            $this->_registerInterestingToken('consumedClass', $tokenNumber);
                            break;
                        case 'INSIDE_NEW_ASSIGNMENT':
                            $this->_registerInterestingToken('consumedClass', $tokenNumber);
                            $context = null;
                            break;
                        case 'INSIDE_FUNCTION_SIGNATURE':
                            $safeWords = array('true', 'false', 'null', 'self', 'parent', 'static');
                            $previousToken = $surroundingTokens[-1];
                            if (in_array($token[1], $safeWords)
                                || (is_array($previousToken) && $previousToken[1] == '::')) {
                                break;
                            }
                            $this->_registerInterestingToken('consumedClass', $tokenNumber);
                            break;
                        case 'INSIDE_CATCH_STATEMENT':
                            $this->_registerInterestingToken('consumedClass', $tokenNumber);
                            $context = null;
                            break;
                    }

                    break;

            }

        }

    }

    protected function _generate()
    {

        $namespace = $this->_fileNameProcessor->getNewNamespace();
        $className = $this->_fileNameProcessor->getNewClassName();

        $prefixesRegex = implode('|', $this->_prefixes);

        if (!preg_match('#^' . $prefixesRegex . '#', $this->_fileNameProcessor->getOriginalClassName())) {
            $this->_newTokens = $this->_originalTokens;
            return;
        }

        // determine consumed classes
        $classCount = array();
        if (isset($this->_interestingTokens['consumedClass'])) {
            foreach ($this->_interestingTokens['consumedClass'] as $consumedClassToken) {
                $origConsumedClassName = $consumedClassToken['value'];
                $consumedClassFileNameProc = $this->_fileRegistry->findByOriginalClassName($origConsumedClassName);
                if ($consumedClassFileNameProc) {
                    $currentConsumedClassName = $consumedClassFileNameProc->getNewFullyQualifiedName();
                } else {
                    $currentConsumedClassName = $origConsumedClassName;
                }

                if (!isset($classCount[$currentConsumedClassName])) $classCount[$currentConsumedClassName] = 0;
                $classCount[$currentConsumedClassName]++;

            }
        }

        // compute uses
        if ($classCount) {

            $uses['declarations'] = $uses['translations'] = $uses = array();

            foreach ($classCount as $consumedClassName => $numberOfOccurances) {
                if ($numberOfOccurances == 1) continue;
                if ((strpos($consumedClassName, '\\') !== false) && (strpos($consumedClassName, $namespace) !== 0)) {
                    $consumedClassFileNameProc = $this->_fileRegistry->findByNewFullyQualifiedName($consumedClassName);
                    $uses['declarations'][] = $ccn = $consumedClassFileNameProc->getNewNamespace();
                    $uses['translations'][$consumedClassName] = substr($ccn, strrpos($ccn, '\\')+1) . '\\'
                        . str_replace($ccn . '\\', '', $consumedClassFileNameProc->getNewFullyQualifiedName());
                }
            }
        }

        foreach ($this->_originalTokens as $tokenNumber => $token) {

            if (!array_key_exists($tokenNumber, $this->_interestingTokenIndex)) {
                $this->_newTokens[] = $token;
                continue;
            }

            // This token is interesting for some reason
            $interestingReasons = $this->_interestingTokenIndex[$tokenNumber];

            foreach ($interestingReasons as $interestingReason) {

                switch ($interestingReason) {
                    case 'topOfFile':
                        $content = 'namespace ' . $namespace . ';' . "\n";
                        if (isset($uses['declarations']) && $uses['declarations']) {
                            foreach ($uses['declarations'] as $useDeclaration) {
                                $content .= 'use ' . $useDeclaration . ';' . "\n";
                            }
                        }
                        $this->_newTokens[] = "\n\n/**\n * @namespace\n */\n" . $content . "\n";
                        break;
                    case 'docblock':
                        if ($this->_canTokenizeDocblocks) {
                            $docblockProc = new DocblockContentProcessor($token[1], $this->_prefixes, $this->_fileRegistry);
                            $this->_newTokens[] = $docblockProc->getContents();
                        } else {
                            $this->_newTokens[] = $token[1];
                        }
                        break;
                    case 'className':
                        $this->_newTokens[] = $className;
                        break;
                    case 'consumedClass':
                        $origConsumedClassName = $token[1];
                        $fileNameProc = $this->_fileRegistry->findByOriginalClassName($origConsumedClassName);
                        if ($fileNameProc) {
                            $newConsumedClass = $fileNameProc->getNewFullyQualifiedName();
                            if (strpos($newConsumedClass, $namespace) === 0) {
                                $newConsumedClass = substr($newConsumedClass, strlen($namespace)+1);
                            } else {
                                $newConsumedClass = '\\' . $newConsumedClass;
                            }
                        } else {
                            $newConsumedClass = '\\' . str_replace('_', '\\', $token[1]);
                        }

                        if (isset($uses['translations']) && $uses['translations'] && $newConsumedClass{0} == '\\') {
                            $translationSearchClass = ltrim($newConsumedClass, '\\');
                            if (array_key_exists($translationSearchClass, $uses['translations'])) {
                                $newConsumedClass = $uses['translations'][$translationSearchClass];
                            }
                        }

                        if (isset($uses['declarations']) && $uses['declarations'] && $newConsumedClass{0} == '\\') {
                            $declarationSearchClass = ltrim($newConsumedClass, '\\');
                            foreach ($uses['declarations'] as $declarationSearchMatch) {
                                if (strpos($declarationSearchClass, $declarationSearchMatch) === 0) {
                                    $newConsumedClass = substr($declarationSearchMatch, strrpos($declarationSearchMatch, '\\')+1) . substr($declarationSearchClass, strlen($declarationSearchMatch));
                                }
                            }
                        }

                        $this->_newTokens[] = $newConsumedClass;
                        break;
                    default:
                        $this->_newTokens[] = $token;
                        break;
                }

            }

        }

    }

    protected function _registerInterestingToken($name, $tokenNumber, $isSingle = false)
    {
        $token = $this->_originalTokens[$tokenNumber];

        if (count($token) != 3) {
            return;
        }

        $tokenObj = new \ArrayObject(
            array(
                'number' => $tokenNumber,
                'id' => $token[0],
                'value' => $token[1],
                'line' => $token[2],
                'name' => token_name($token[0])
            ),
            \ArrayObject::ARRAY_AS_PROPS
        );

        if ($isSingle) {
            $this->_interestingTokens[$name] = $tokenObj;
        } else {
            if (!isset($this->_interestingTokens[$name])) {
                $this->_interestingTokens[$name] = array();
            }
            $this->_interestingTokens[$name][] = $tokenObj;
        }

        if (!isset($this->_interestingTokenIndex[$tokenNumber])) {
            $this->_interestingTokenIndex[$tokenNumber] = array();
        }

        $this->_interestingTokenIndex[$tokenNumber][] = $name;
    }

    public function __toString()
    {
        return 'File Contents for:  ' . $this->_fileNameProcessor->getOriginalFilePath();
    }


}