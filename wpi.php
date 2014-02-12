#!/usr/bin/env php
<?php
require_once 'autoload.php';

class wpi extends bindata
{
  function wpi($filename)
  {
    $this->load($filename);
  }

  function process()
  {
    $ret=new wpiDrawing();
    $unknownPurpose=$this->popBlock(2059);
    while(!$this->eof()) {
      $blockDescription=$this->popLittleEndian(1);
      $blockSize=$this->popLittleEndian(1);
      // $blockSize is the length of the block WITH the 2-bytes header
      $blockBody=$this->popBlock($blockSize-2);
      $body=new bindata($blockBody);
      switch($blockDescription)
      {
        case 241: // Stroke wpiLayer Description
          switch($body->popChar())
          {
            case 128: // New wpiLayer
              $ret->addLayer();
              break;
            case 01: // Start Stroke
              $ret->startStroke();
              break;
            case 00: // End Stroke
              $ret->endStroke();
              break;
            default: // ...fuck the rest
              break;
          }
          break;
        case  97: // Pen XY Data
          $ret->addPoint($body->popNetworkShort(),$body->popNetworkShort());
          break;
        case 100: // Pen Pressure
          $body->skipBlock(2); // skip a cuppa bytes (IDK why)
          $ret->addPressure($body->popNetworkShort());
          break;
        case 101: // Pen Tilt
          $ret->addTilt($body->popNetworkShort());
          break;
        default : // All Others
          // fuck'em
          break;
      }
    }
    return $ret;
  }
}

/*main*/
$parser=new wpi('SKETCH11.WPI');
$drawing=$parser->process();
$renderer=new wpiRenderer($drawing);
$renderer->toSVG('some.svg');

class wpiLayer
{
  function wpiLayer()
  {
    $this->strokes=array();
  }

  function startStroke()
  {
    $this->strokes[]=array('S');
  }

  function endStroke()
  {
    $this->strokes[]=array('E');
  }

  function addPoint($x,$y)
  {
    $this->strokes[]=array('x',$x,$y);
  }

  function addPressure($pressure)
  {
    $this->strokes[]=array('p',$pressure);
  }

  function addTilt($pressure)
  {
    $this->strokes[]=array('t',$pressure);
  }

  function __tostring()
  {
    $ret='';
    foreach($this->strokes as $v)
    {
      $ret.=implode(' ',$v)."\n";
    }
    return $ret;
  }
}

class wpiDrawing
{
  function wpiDrawing()
  {
    $this->idx=0;
    $this->layers=array(new wpiLayer());
  }

  function addLayer()
  {
    $this->layers[]=new wpiLayer();
    $this->idx++;
  }

  function startStroke()
  {
    $layer=$this->currentLayer();
    $layer->startStroke();
  }

  function endStroke()
  {
    $layer=$this->currentLayer();
    $layer->endStroke();
  }

  function addPoint($x,$y)
  {
    $layer=$this->currentLayer();
    $layer->addPoint($x,$y);
  }

  function addPressure($pressure)
  {
    $layer=$this->currentLayer();
    $layer->addPressure($pressure);
  }

  function addTilt($tilt)
  {
    $layer=$this->currentLayer();
    $layer->addTilt($tilt);
  }

  function currentLayer()
  {
    return $this->layers[$this->idx];
  }

  function __tostring()
  {
    $ret='';
    foreach($this->layers as $layer)
    {
      $ret.=$layer->__tostring();
    }
    return $ret;
  }

  function strokes()
  {
    $ret=array();
    foreach($this->layers as $layer)
    {
      array_splice($ret,count($ret),0,$layer->strokes);
    }
    return $ret;
  }
}

class wpiRenderer
{
  function wpiRenderer($drawing)
  {
    $this->drawing=$drawing;
    $this->title='';
    $this->desc='';
  }

  function toSVG($filename='')
  {
    list($x1,$y1,$x2,$y2)=$this->findRange();
    $w=$x2-$x1; $W=$w+10; $Wcm='21cm';
    $h=$y2-$y1; $H=$h+10; $Hcm=(21*$h/$w).'cm';
    $d="M 100 100 L 300 100 L 200 300 z"; // TODO actual rendering
    $ret="<?xml version='1.0' standalone='no'?>
<!DOCTYPE svg PUBLIC '-//W3C//DTD SVG 1.1//EN' 'http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd'>
<svg width='$Wcm' height='$Hcm' viewBox='0 0 $W $H' xmlns='http://www.w3.org/2000/svg' version='1.1'><title>{$this->title}</title><desc>{$this->desc}</desc>
<rect x='5' y='5' width='$w' height='$h' fill='none' stroke='blue' />
<path d='$d' fill='red' stroke='blue' stroke-width='3' />
</svg>";
  

    if($filename)
      file_put_contents($filename,$ret);
    else
      return $ret;
  }

  function findRange()
  {
    $d=$this->drawing;
    $minX=$minY=null;
    $maxX=$maxY=null;
    foreach($d->strokes() as $v) {
      @list($t,$x,$y)=$v;
      switch($t){
        case 'x':
          if($minX===null) $minX=$maxX=$x;
          if($minY===null) $minY=$maxY=$y;
          if($x>$maxX) $maxX=$x;
          if($y>$maxY) $maxY=$y;
          if($x<$minX) $minX=$x;
          if($y<$minY) $minY=$y;
      }
    }
    return array($minX,$minY,$maxX,$maxY);
  }
}
