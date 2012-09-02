<?php
require_once 'DocBlockParser.php';
require_once 'YiiComponentPropertyIterator.php';
require_once 'ModelFilesIterator.php';

class Generator extends CComponent
{
    public $baseClass = 'CModel';
    public $toUndercore = false;

    public $filesIterator = 'ModelFilesIterator';
    public $propertyIterator = 'YiiComponentPropertyIterator';

    public function getFilesIterator()
    {
        return new $this->filesIterator;
    }

    public function getPropertyIterator($object)
    {
        $class = $this->propertyIterator;
        return new $class($object);
    }

    public function generate()
    {
        foreach ($this->getFilesIterator() as $fileInfo)
        {
            if (!$fileInfo->isFile())
            {
                continue;
            }

            $this->addDocBlockFile($fileInfo);
        }
    }


    public function addDocBlockFile($fileInfo)
    {
        try
        {
            $class = pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME);
            $object = new $class;
            if (!$object instanceof $this->baseClass)
            {
                return false;
            }
        } catch (Exception $e)
        {
            return false;
        }

        $parser   = DocBlockParser::parseClass($class);
        $docBlock = $this->getDockBlock($parser, $this->getPropertyIterator($object));
        dump($docBlock);
        $file        = $fileInfo->getPath() . '/' . $fileInfo->getFileName();
        $content     = file_get_contents($file);
        $fileContent = substr($content, strpos($content, "class $class"));
        file_put_contents($file, '<?php' . PHP_EOL . $docBlock . PHP_EOL . $fileContent);
    }


    public function getDockBlock(DocBlockParser $parser, Iterator $props)
    {
        $docBlock = "";
        //description
        if ($parser->shortDescription)
        {
            $docBlock .= $parser->shortDescription . "\n\n";
        }
        if ($parser->longDescription)
        {
            $docBlock .= $parser->longDescription . "\n\n";
        }

        //properties
        foreach ($props as $prop => $data)
        {
            $name = $this->toUndercore ? Yii::app()->text->camelCaseToUnderscore($prop) : $prop;

            if ($data['settable'] && $data['gettable'] && ($data['writeType'] == $data['readType']) &&
                ($data['writeComment'] == $data['readComment'])
            )
            {
                $docBlock .= $this->getOneLine($parser, $name, null, $data);
            }
            else
            {
                if ($data['settable'])
                {
                    $docBlock .= $this->getOneLine($parser, $name, 'write', $data);
                }
                if ($data['gettable'])
                {
                    $docBlock .= $this->getOneLine($parser, $name, 'read', $data);
                }
            }
        }
        $docBlock .= "\n";

        //authors
        if ($parser->authors)
        {
            foreach (explode("\n", $parser->authors) as $line)
            {
                $docBlock .= "@author $line\n";
            }
        }

        //add commets and stars :-)
        $result = "/** \n";
        foreach (explode("\n", $docBlock) as $line)
        {
            $result .= " * " . trim($line) . "\n";
        }
        return $result . " */\n";
    }


    public function getOneLine(DocBlockParser $parser, $name, $mode, $data)
    {
        $commentKey   = $mode ? $mode . "Comment" : 'writeComment';
        $typeKey      = $mode ? $mode . "Type" : 'writeType';
        $nameKey      = $mode ? $name . '-' . $mode : $name;
        $propertyType = $mode ? 'property-' . $mode : "property";

        $oldComment = isset($parser->properties[$nameKey]) ? $parser->properties[$nameKey]['comment'] : '';
        $comment    = $data[$commentKey] ? $data[$commentKey] : $oldComment;
        $oldType    = isset($parser->properties[$nameKey]) ? $parser->properties[$nameKey]['type'] : '';
        $type       = $data[$typeKey] ? $data[$typeKey] : $oldType;
        return "@$propertyType $type \$$name $comment\n";
    }
}
