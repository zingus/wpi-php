<?php
require_once 'autoload.php';

class bindata
{
  function bindata($data='')
  {
    $this->feed($data);
  }

  function feed($data)
  {
    $this->idx=0;
    $this->len=strlen($data);
    $this->data=$data;
  }

  function load($filename)
  {
    $this->idx=0;
    $this->len=filesize($filename);
    $this->data=file_get_contents($filename);
  }

  function popBlock($len)
  {
    $i=$this->idx;
    $this->idx+=$len;
    $ret=substr($this->data,$i,$len);
    //echo $i.':'.$this->idx.' ['.ord(substr($ret,0,1)).','.ord(substr($ret,-1,1))."]\n";
    return $ret;
  }

  function skipBlock($len)
  {
    $this->idx+=$len;
  }

  function popLittleEndian($len)
  {
    $ret=0;
    $data=$this->popBlock($len);
    for($i=0;$i<$len;$i++)
    {
      $ret+=ord($data[$i]);
    }
    return $ret;
  }

  function popBigEndian($len)
  {
    $ret=0;
    $data=$this->popBlock($len);
    for($i=$len-1;$i>=0;$i--)
    {
      $ret+=ord($data[$i]);
    }
    return $ret;
  }

  function popShort()
  {
    return $this->popLittleEndian(2);
  }

  function popNetworkShort()
  {
    return $this->popBigEndian(2);
  }

  function popInt()
  {
    return $this->popLittleEndian(4);
  }

  function popNetworkInt()
  {
    return $this->popBigEndian(4);
  }

  function popChar()
  {
    return $this->popLittleEndian(1);
  }

  function eof()
  {
    return $this->idx>=$this->len;
  }
}

