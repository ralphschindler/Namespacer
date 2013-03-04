<?php

namespace Namespacer\Model;

use Zend\Code\Scanner\FileScanner;

class Mapper
{
    public function getMapDataForDirectory($directory)
    {
        $datas = array();

        $rdi = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($rdi) as $file) {
            /** @var $file \SplFileInfo */
            if ($file->getExtension() != 'php') {
                continue;
            }
            foreach ($this->getMapDataForFile($file->getRealPath()) as $data) {
                $datas[] = $data;
            }
        }
        return $datas;
    }

    public function getMapDataForFile($file)
    {
        $file = realpath($file);

        $datas = array();
        $fs = new FileScanner($file);
        // problem in TokenArrayScanner.php line #579, needs fix (notice undefined offset 1)
        @$classes = $fs->getClassNames();
        foreach ($classes as $class) {

            $newNamespace = str_replace('_', '\\', substr($class, 0, strrpos($class, '_')));
            if (strpos($class, '_') !== false) {
                $newClass = substr($class, strrpos($class, '_')+1);
            } else {
                $newClass = $class;
            }

            $rootDir = $this->findRootDirectory($file, $class);
            if ($newNamespace) {
                $newFile = $rootDir . DIRECTORY_SEPARATOR
                    . str_replace('\\', DIRECTORY_SEPARATOR, $newNamespace) . DIRECTORY_SEPARATOR
                    . $newClass . '.php';
            } else {
                $newFile = $file;
            }


            //$root = substr($file, 0, strpos($file, str_replace('\\', DIRECTORY_SEPARATOR, $newNamespace)));

            $data = array(
                'root_directory' => $rootDir,
                'original_class' => $class,
                'original_file' => $file,
                'new_namespace' => $newNamespace,
                'new_class' => $newClass,
                'new_file' => $newFile,
            );

            // per-file transformations
            $this->transformInterfaceName($data);
            $this->transformAbstractName($data);
            $this->transformReservedWords($data);

            $datas[] = $data;

            // per-set transformationss
        }

        return $datas;
    }

    protected function findRootDirectory($file, $class)
    {
        $rootDirParts = array_reverse(explode(DIRECTORY_SEPARATOR, $file));
        $classParts = array_reverse(explode('_', $class));

        // remove file/class
        array_shift($rootDirParts);
        array_shift($classParts);

        if (count($classParts) === 0) {
            return implode(DIRECTORY_SEPARATOR, array_reverse($rootDirParts));
        }

        while (true) {
            $curDirPart = reset($rootDirParts);
            $curClassPart = reset($classParts);
            if ($curDirPart === false || $curClassPart === false) {
                break;
            }
            if ($curDirPart === $curClassPart) {
                array_shift($rootDirParts);
                array_shift($classParts);
            } else {
                break;
            }
        }

        return implode(DIRECTORY_SEPARATOR, array_reverse($rootDirParts));
    }

    protected function transformInterfaceName(&$data)
    {
        if (strtolower($data['new_class']) !== 'interface') {
            return;
        }

        $nsParts = array_reverse(explode('\\', $data['new_namespace']));
        $data['new_class'] = $nsParts[0] . 'Interface';

        $data['new_file'] = $data['root_directory'] . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $data['new_namespace']) . DIRECTORY_SEPARATOR
            . $data['new_class'] . '.php';
    }

    protected function transformAbstractName(&$data)
    {
        if (strtolower($data['new_class']) !== 'abstract') {
            return;
        }

        $nsParts = array_reverse(explode('\\', $data['new_namespace']));
        $data['new_class'] = 'Abstract' . $nsParts[0];

        $data['new_file'] = $data['root_directory'] . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $data['new_namespace']) . DIRECTORY_SEPARATOR
            . $data['new_class'] . '.php';
    }

    protected function transformReservedWords(&$data)
    {
        /*
        static $reservedWords = array(
            'and','array','as','break','case','catch','class','clone',
            'const','continue','declare','default','do','else','elseif',
            'enddeclare','endfor','endforeach','endif','endswitch','endwhile',
            'extends','final','for','foreach','function','global',
            'goto','if','implements','instanceof','namespace',
            'new','or','private','protected','public','static','switch',
            'throw','try','use','var','while','xor'
        );

        if ($data['new_class'] !== 'Interface') {
            return;
        }

        $nsParts = array_reverse(explode('\\', $data['new_namespace']));
        $data['new_class'] = $nsParts[0] . 'Interface';

        $data['new_file'] = $data['root_directory'] . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $data['new_namespace']) . DIRECTORY_SEPARATOR
            . $data['new_class'] . '.php';
        */
    }

}
