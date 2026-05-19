<?php
function startSession():void{ if(session_status()===PHP_SESSION_NONE) session_start(); }
function isAdmin():bool{ startSession(); return isset($_SESSION['user_id'])&&$_SESSION['role']==='admin'; }
function requireAdmin():void{ if(!isAdmin()){ header('Location:'.url('login.php')); exit; } }
function setFlash(string $t,string $m):void{ startSession(); $_SESSION['flash']=compact('t','m'); }
function getFlash():?array{
    startSession();
    if(isset($_SESSION['flash'])){ $f=$_SESSION['flash']; unset($_SESSION['flash']); return ['type'=>$f['t'],'msg'=>$f['m']]; }
    return null;
}
