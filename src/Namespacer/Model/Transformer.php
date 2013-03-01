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
}