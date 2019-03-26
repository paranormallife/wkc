<?php
namespace WPSynchro;

interface RemotePOST
{

    public function remotePOST($url, $args);
}

class WPRemotePOST implements RemotePOST
{

    public function remotePOST($url, $args)
    {
        return \wp_remote_post($url, $args);
    }
}

class TestRemotePOST implements RemotePOST
{

    public $shallreturn = null;

    public function remotePOST($url, $args)
    {
        return $this->shallreturn;
    }
}
