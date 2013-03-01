<?php

class DocblockContentProcessor
{

    protected $_content = null;
    protected $_prefixes = array();
    protected $_newTokens = array();

    /**
     * @var \Namespacer\FileRegistry
     */
    protected $_fileRegistry = null;

    public function __construct($docblockContent, $prefixes = array(), FileRegistry $fileRegistry = null)
    {
        $this->_content = $docblockContent;
        $this->_prefixes = $prefixes;
        $this->_fileRegistry = $fileRegistry;
        $this->_generate();
    }

    public function getContents()
    {
        $content = '';
        foreach ($this->_newTokens as $newToken) {
            $content .= $newToken[1];
        }
        return $content;
    }

    protected function _generate()
    {
        $tokens = docblock_tokenize($this->_content);
        $context = null;

        $this->_newTokens = array();

        foreach ($tokens as $token) {
            $newToken = array();
            $tokenName = docblock_token_name($token[0]);
            switch ($tokenName) {
                case 'DOCBLOCK_TAG':
                    switch ($token[1]) {
                        case '@var':
                        case '@uses':
                        case '@param':
                        case '@return':
                        case '@throws':
                            $context = 'INSIDE_TYPE_TAG';
                            break;
                    }
                    $this->_newTokens[] = $token;
                    break;
                case 'DOCBLOCK_TEXT':
                    if ($context == 'INSIDE_TYPE_TAG') {
                        $matches = array();
                        $prefixRegex = implode('|', $this->_prefixes);
                        if (preg_match('#.*(' . $prefixRegex . '_[\\w_]*).*#', $token[1], $matches)) {
                            $className = $matches[1];
                            $fileNameProc = $this->_fileRegistry->findByOriginalClassName($className);
                            if ($fileNameProc) {
                                $newClassName = '\\' . $fileNameProc->getNewFullyQualifiedName();
                                $tokenContent = preg_replace('#' . $className . '#', $newClassName, $matches[0]);
                                $newToken[1] = $tokenContent;
                                $this->_newTokens[] = $newToken;
                            } else {
                                $this->_newTokens[] = $token;
                            }

                        } else {
                            $this->_newTokens[] = $token;
                        }
                        $context = null;
                    } else {
                        $this->_newTokens[] = $token;
                    }
                    break;
                default:
                    $this->_newTokens[] = $token;
                    break;
            }
        }

    }

}
