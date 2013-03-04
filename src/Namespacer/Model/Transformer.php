<?php

namespace Namespacer\Model;

use Zend\Code\Scanner\FileScanner;

class Transformer
{
    /** @var \Namespacer\Model\Map */
    protected $map;

    public function __construct(Map $map)
    {
        $this->map = $map;
    }

    public function moveFiles()
    {
        $fileRenamings = $this->map->getFileRenamings();
        $this->validateFileRenamings($fileRenamings);
        foreach ($fileRenamings as $old => $new) {
            if ($old == $new) {
                continue;
            }
            //echo 'moving ' . $old . ' => ' . $new . PHP_EOL;
            $newDir = dirname($new);
            if (!file_exists($newDir)) {
                mkdir($newDir, 0777, true);
            }
            rename($old, $new . '.transform');
        }
        foreach ($fileRenamings as $new) {
            if (file_exists($new . '.transform')) {
                rename($new . '.transform', $new);
            }
        }
    }

    public function modifyNamespaceAndClassNames()
    {
        $fileNames = $this->map->getNameModifications();
        foreach ($fileNames as $file => $names) {
            if (!file_exists($file)) {
                throw new \RuntimeException('The file ' . $file . ' could not be found in the filesystem, check your map file is correct.');
            }
            $this->modifyFileWithNewNamespaceAndClass($file, $names);
        }
    }

    public function modifyContentForUseStatements()
    {
        $files = $this->map->getNewFiles();
        $classTransformations = $this->map->getClassTransformations();
        foreach ($files as $file) {
            if (!file_exists($file)) {
                throw new \RuntimeException('The file ' . $file . ' could not be found in the filesystem, check your map file is correct.');
            }
            $this->modifyFileWithNewUseStatements($file, $classTransformations);
        }
    }

    protected function validateFileRenamings(array $fileRenamings)
    {
        $processed = array();
        foreach ($fileRenamings as $old => $new) {
            if (array_search($new, $processed)) {
                throw new \Exception('The new file ' . $new . ' is in the type map more than once, ensure there is no collision in the map file');
            }
            $processed[] = $new;
        }
        return;
    }

    protected function modifyFileWithNewNamespaceAndClass($file, $names)
    {
        $tokens = token_get_all(file_get_contents($file));

        if ($names['namespace'] == '') {
            return;
        }

        switch (true) {
            case ($tokens[0][0] === T_OPEN_TAG && $tokens[1][0] === T_DOC_COMMENT):
                array_splice($tokens, 2, 0, "\n\nnamespace {$names['namespace']};\n");
                break;
            default:
                array_splice($tokens, 1, 0, "\n\nnamespace {$names['namespace']};\n");
                break;
        }

        $contents = '';
        $token = reset($tokens);
        do {
            if ($token[0] === T_CLASS) {
                $contents .= 'class ' . $names['class'];
                next($tokens); next($tokens);
            } else {
                $contents .= (is_array($token)) ? $token[1] : $token;
            }
        } while ($token = next($tokens));

        file_put_contents($file, $contents);
    }

    protected function modifyFileWithNewUseStatements($file, $classTransformations)
    {
        $tokens = token_get_all(file_get_contents($file));

        $ti = array();
        $token = reset($tokens);
        $normalTokens = array();
        $interestingTokens = array();

        $hasNamespace = false;
        $usePlacement = 1;
        $currentClass = '';

        do {
            $normalTokens[] = ((is_array($token)) ? $token[0] : $token) . PHP_EOL;
        } while ($token = next($tokens));

        foreach ($normalTokens as $i => $t1) {
            $t2 = isset($normalTokens[$i+1]) ? $normalTokens[$i+1] : null;
            $t3 = isset($normalTokens[$i+2]) ? $normalTokens[$i+2] : null;

            // find if it has a namespace
            if ($i < 3 && $t1 == T_OPEN_TAG) {
                $usePlacement = $i + 1;
                if ($t2 == T_DOC_COMMENT) {
                    $usePlacement = $i + 2;
                }
            }

            if ($t1 == T_NAMESPACE) {
                $hasNamespace = true;
                $usePlacement = $i + 1;
            }

            // find current class name (used for collions in uses)
            if ($t1 == T_CLASS && $t2 == T_WHITESPACE && $t3 == T_STRING) {
                $currentClass = $tokens[$i+2][1];
                continue;
            }

            // constant usage
            if ($t1 == T_STRING && $t2 == T_DOUBLE_COLON && $t3 == T_STRING) {
                if (!in_array($tokens[$i][1], array('self', 'parent'))) {
                    $interestingTokens[] = $i;
                    continue;
                }
            }

            // instanceof
            if ($t1 == T_INSTANCEOF && $t2 == T_WHITESPACE && $t3 == T_STRING) {
                if (!in_array($tokens[$i+2][1], array('self', 'parent'))) {
                    $interestingTokens[] = $i+2;
                }
                continue;
            }

            // new
            if ($t1 == T_NEW && $t2 == T_WHITESPACE && $t3 == T_STRING) {
                if (!in_array($tokens[$i+2][1], array('self', 'parent'))) {
                    $interestingTokens[] = $i+2;
                }
                continue;
            }

            // extends
            if ($t1 == T_EXTENDS && $t2 == T_WHITESPACE && $t3 == T_STRING) {
                $interestingTokens[] = $i+2;
                continue;
            }

            // implements
            if ($t1 == T_IMPLEMENTS && $t2 == T_WHITESPACE && $t3 == T_STRING) {
                $u = $i + 1;
                do {
                    if ($normalTokens[$u] == T_STRING) {
                        $interestingTokens[] = $u;
                    } elseif ($normalTokens[$u] == '{' || $normalTokens[$u] == "{\n") {
                        continue 2;
                    }
                    $u++;
                } while (isset($normalTokens[$u]) && $normalTokens[$u] !== '{');
                continue;
            }

            // type-hints
            if ($t1 == T_STRING && $t2 == T_WHITESPACE && $t3 == T_VARIABLE) {
                $interestingTokens[] = $i;
            }
        }

        $uniqueUses = array();

        foreach ($interestingTokens as $index) {
            $name = $tokens[$index][1];
            if (!isset($uniqueUses[$name])) {
                $uniqueUses[$name] = (isset($classTransformations[$name])) ? $classTransformations[$name] : $name;
            }
        }

        $shortNames = array();

        // cleanup unique uses
        foreach ($uniqueUses as $newName) {
            $shortName = (($shortNameStart = strrpos($newName, '\\')) !== false) ? substr($newName, $shortNameStart+1) : $newName;
            $shortNames[$newName] = $shortName;
        }

        $shortNamesCount = array_count_values($shortNames);
        $dupShortNames = array_filter($shortNames, function ($item) use ($shortNamesCount, $currentClass) { return (($shortNamesCount[$item] >= 2) || $item == $currentClass); });

        $useContent = '';

        foreach ($shortNames as $fqcn => $sn) {
            if (isset($dupShortNames[$fqcn])) {
                $useContent .= "use $fqcn as " . str_replace('\\', '', $fqcn) . ";\n";
                $shortNames[$fqcn] = str_replace('\\', '', $fqcn);
            } else {
                $useContent .= "use $fqcn;\n";
            }
        }

        $contents = '';
        $token = reset($tokens);

        do {
            if (key($tokens) == $usePlacement) {
                if ($hasNamespace) {
                    do {
                        $contents .= (is_array($token)) ? $token[1] : $token;
                    } while (($token = next($tokens)) !== false && !(is_string($token) && $token == ';'));
                    $contents .= ';';
                } else {
                    $contents .= (is_array($token)) ? $token[1] : $token;
                }
                $contents .= "\n\n$useContent\n";
            } elseif (array_search(key($tokens), $interestingTokens) !== false) {
                $contents .= $shortNames[$uniqueUses[$token[1]]];
            } else {
                $contents .= (is_array($token)) ? $token[1] : $token;
            }
        } while ($token = next($tokens));

        file_put_contents($file, $contents);
    }

}
