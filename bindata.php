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

  function readBlock($len)
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

  function readLittleEndian($len)
  {
    $ret=0;
    $data=$this->readBlock($len);
    for($i=0;$i<$len;$i++)
    {
      $ret+=ord($data[$i]);
    }
    return $ret;
  }

  function readBigEndian($len)
  {
    $ret=0;
    $data=$this->readBlock($len);
    for($i=$len-1;$i>=0;$i--)
    {
      $ret+=ord($data[$i]);
    }
    return $ret;
  }

  function readShort()
  {
    return $this->readLittleEndian(2);
  }

  function readNetworkShort()
  {
    return $this->readBigEndian(2);
  }

  function readInt()
  {
    return $this->readLittleEndian(4);
  }

  function readNetworkInt()
  {
    return $this->readBigEndian(4);
  }

  function readByte()
  {
    return $this->readLittleEndian(1);
  }

  function eof()
  {
    return $this->idx>=$this->len;
  }
}

