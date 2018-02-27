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
    $unknownPurpose=$this->readBlock(2059);
    while(!$this->eof()) {
      $blockDescription=$this->readLittleEndian(1);
      $blockSize=$this->readLittleEndian(1);
      // $blockSize is the length of the block WITH the 2-bytes header
      $blockBody=$this->readBlock($blockSize-2);
      $body=new bindata($blockBody);
      switch($blockDescription)
      {
        case 241: // Stroke wpiLayer Description
          switch($body->readByte())
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
          $x=$body->readNetworkShort();
          $y=$body->readNetworkShort();
          $ret->addPoint($x,$y*2+5);
          break;
        case 100: // Pen Pressure
          $body->skipBlock(2); // skip a cuppa bytes (IDK why)
          $ret->addPressure($body->readNetworkShort());
          break;
        case 101: // Pen Tilt
          $ret->addTilt($body->readNetworkShort());
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
$parser=new wpi('SKETCH5.WPI');
$drawing=$parser->process();
file_put_contents('dump.txt',"".$drawing);
$renderer=new wpiRenderer($drawing);
$renderer->toSVG('some.svg');

class wpiLayer
{
  function wpiLayer()
  {
    $this->strokes=array(new wpiStroke());
    $this->idx=0;
  }

  function startStroke()
  {
    $stroke=$this->currentStroke();
    if(!$stroke->isEmpty())
      $this->addStroke();
  }

  function endStroke()
  {
    //$this->addStroke();
  }

  function addStroke()
  {
    $this->idx++;
    $this->strokes[$this->idx]=new wpiStroke();
  }

  function addPoint($x,$y)
  {
    $stroke=$this->currentStroke();
    $stroke->addPoint($x,$y);
  }

  function addPressure($pressure)
  {
    $stroke=$this->currentStroke();
    $stroke->addPressure($pressure);
  }

  function addTilt($tilt)
  {
    $stroke=$this->currentStroke();
    $stroke->addTilt($tilt);
  }

  function __tostring()
  {
    $ret='';
    foreach($this->strokes as $stroke)
    {
      //$ret.=implode(' ',$v)."\n";
      $ret.=$stroke->__tostring();
    }
    return $ret;
  }

  function currentStroke()
  {
    return $this->strokes[$this->idx];
  }
}

class wpiStroke
{
  function wpiStroke()
  {
    $this->coordX   = array();
    $this->coordY   = array();
    $this->pressure = array();
    $this->tilt     = array();
  }

  function isEmpty()
  {
    return count($this->coordX)===0;
  }

  function addPoint($x,$y)
  {
    $this->coordX[]=$x;
    $this->coordY[]=$y;
  }

  function addPressure($pressure)
  {
    $this->pressure[]=$pressure;
  }

  function addTilt($tilt)
  {
    $this->tilt[]=$tilt;
  }

  function __tostring()
  {
    $ret="<stroke>\n";
    foreach($this->coordX as $k=>$x) {
      $ret.=sprintf("  x %3d y %3d p %3d t %3d\n",$x,$this->coordY[$k],$this->pressure[$k],$this->tilt[$k]);
    }
    $ret.="</stroke>\n";
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
      $ret.="<layer>\n  ".trim(strtr($layer->__tostring(),array("\n"=>"\n  ")))."\n</layer>\n";
    }
    return $ret;
  }

  function strokes()
  {
    $ret=array();
    foreach($this->layers as $layer)
    {
      array_splice($ret,count($ret),0,$layer->strokes); // append
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
    $drawing=$this->drawing;
    list($x1,$y1,$x2,$y2)=$this->findRange();
    $w=$x2-$x1; $W=$w+10; $Wcm='21cm';
    $h=$y2-$y1; $H=$h+10; $Hcm=(21*$h/$w).'cm';
    $paths='';
    foreach($drawing->strokes() as $stroke) {
      $d='';
      foreach($stroke->coordX as $k=>$x) {
        if($stroke->pressure[$k]<100) continue; // ignore point with a low pressure
        $y=$stroke->coordY[$k];
        if($k==0)
          $d.="M $x $y L $x $y ";
        // normalize coords
        $x-=$x1;
        $y-=$y1;
        // append to path data
        //$d.="$cmd $x $y ";
        $d.="$x $y ";
      }
      $d.='z';
      //$color='#'.substr(md5($k),0,6);
      $r=($k<16?'0':'').strtoupper(dechex(255-$k%255));
      $color="#{$r}00{$r}";
      $paths.="<path d='$d' fill='none' stroke='$color' stroke-width='1' />\n";
    }
    $ret="<?xml version='1.0' standalone='no'?>
<!DOCTYPE svg PUBLIC '-//W3C//DTD SVG 1.1//EN' 'http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd'>
<svg width='$Wcm' height='$Hcm' viewBox='0 0 $W $H' xmlns='http://www.w3.org/2000/svg' version='1.1'><title>{$this->title}</title><desc>{$this->desc}</desc>
<rect x='5' y='5' width='$w' height='$h' fill='none' stroke='blue' />
$paths
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
    foreach($d->strokes() as $stroke) {
      if($minX===null) $minX=$maxX=$stroke->coordX[0];
      if($minY===null) $minY=$maxY=$stroke->coordY[0];
      foreach($stroke->coordX as $x) {
          if($x>$maxX) $maxX=$x;
          if($x<$minX) $minX=$x;
      }
      foreach($stroke->coordY as $y) {
          if($y>$maxY) $maxY=$y;
          if($y<$minY) $minY=$y;
      }
    }
    return array($minX,$minY,$maxX,$maxY);
  }
}
