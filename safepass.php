#!/usr/bin/env php
<?php

/* -- DATA GOES HERE you shall not edit it manualy -- */

$db = 'eNqrVsosU7JSqtQsd8wwLTcK8YoKCUqPUapSqgUAbVwINw==';

/* -- END OF DATA -- */

$selfname = basename(array_shift($argv));
exit((new SafePass)->run($selfname, $argv, $db));

class SafePass
{
    /* -- YOU MAY EDIT const to fit your needs -- */
    
    const VERSION = '0.2';
    const PERMISSIONS = 0700;
    const PERMISSIONS_MK = 0400;
    const CIPHER = 'aes-256-cbc';

    /* 4 types of chars, lowercase, upercase, special, numeric */
    const PASS_CHARS = [
        'abcdefghijklmnopqrstuvwxyz',
        'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        '+-*/()[]&"_)',
        '0123456789'
    ];

    /* rules for password generation */
    const PASS_RULES = [
        16,/* password length */
        6, /* minimum lowercase chars */
        6, /* exact uppercase chars */
        2, /* exact special chars */
        2  /* exact numerical chars */        
    ];
    
    private $name, $db;
    private $mkrfile;
    private $mkhfile;    
    private $cols = ['SERVICE', 'EMAIL', 'USERNAME', 'PASSWORD'];
    
    public function run($selfname, $args, $db)
    {
        $this->name = $selfname;
        $this->db = json_decode(gzuncompress(base64_decode($db)));
        $this->mkrfile = '/run/user/' . getmyuid() . '/' . $this->name . '.mk';
        $this->mkrfile = str_replace('/0/', '/', $this->mkrfile);
        $this->mkhfile = getenv('HOME') . '/.' . $this->name . '.mk';
            
        $cmd = '_' . (array_shift($args) ?? 'help');
        if(method_exists($this, $cmd)){
            return $this->$cmd(...$args);
        }        
        echo $this->name . ": " . substr($cmd, 1) . ": subcommand not found\n";
        echo "Try $this->name help for more info.\n";
        return 1;
    }

    private function genPassword($len = null, $lc = null, $uc = null, $spec = null, $num = null)        
    {
        $len = is_null($len) ? self::PASS_RULES[0] : (int) $len;
        $lc = is_null($lc) ? self::PASS_RULES[1] : (int) $lc;
        $uc = is_null($uc) ? self::PASS_RULES[2] : (int) $uc;
        $spec = is_null($spec) ? self::PASS_RULES[3] : (int) $spec;
        $num = is_null($num) ? self::PASS_RULES[4] : (int) $num;        
        $pass = '';
        $conf = [$lc, $uc, $spec, $num];       
        if($len < array_sum($conf)){
            echo "$this->name: error: bad password rules\n";
            exit(2);
        }
        foreach($conf as $k => $n){
            $pass.= $this->pick($n, self::PASS_CHARS[$k]);
        }
        $pass.= $this->pick(($len - array_sum($conf)), self::PASS_CHARS[0]);
        $pass = str_split($pass);
        shuffle($pass);
        return implode($pass);
    }

    private function pick($n, $str)
    {
        $res = '';
        $len = strlen($str);
        for($i = 0; $i < $n; $i++){
            $rand = random_int(0, $len - 1);            
            $res.= $str[$rand];
        }        
        return $res;        
    }
    
    private function getColsLen($arr)
    {
        $len = [];
        foreach($this->cols as $k){
            $len[] = strlen($k) + 1;
        }        
        foreach($arr as $a){
            foreach([0,1,2,3] as $k){
                $l = strlen($a[$k]) + 1;
                if($len[$k] < $l){
                    $len[$k] = $l;
                }
            }
        }
        return $len;
    }
    
    private function genIV()
    {
        $ivlen = openssl_cipher_iv_length(self::CIPHER);
        return($this->genPassword($ivlen));
    }
    
    private function mkPrompt($text = 'Enter master key: ')
    {
        echo $text;
        `stty -echo`;
        $password = trim(fgets(STDIN));
        `stty echo`;
        echo "\n";
        return $password;      
    }

    private function getMasterKey()
    {        
        if(is_file($this->mkrfile)){
            $mk = trim(file($this->mkrfile)[0]);
        } elseif(is_file($this->mkhfile)){
            $mk = trim(file($this->mkhfile)[0]);
        } else {
            $mk = trim($this->mkPrompt());
            if(!isset($this->db->accounts)){
                return $mk;
            }
        }
        if(isset($this->db->accounts) &&
           (openssl_decrypt($this->db->accounts, self::CIPHER, $mk, 0, $this->db->iv) === false)){
            return false;
        }
        return $mk;
    }
   
    private function save()
    {
        $file = __FILE__;        
        $code = '';        
        $line = file($file);

        for($i = 0; $i < count($line); $i++){
            $mark = substr(trim($line[$i]), 0, 3);
            if($mark !== '$db'){
                $code.= $line[$i];
            } else {
                break;
            }
        }
        $assign = ' = ';
        foreach(str_split(base64_encode(gzcompress(json_encode($this->db),9)), 76) as $str){
            $code.= '$db' . $assign . "'" . $str . "';" . "\n";
            if($assign === ' = '){
                $assign = '.= ';
            }            
        }
        for(; $i < count($line); $i++){
            $mark = substr(trim($line[$i]), 0, 3);
            if($mark !== '$db'){
                break;
            }
        }
        for(; $i < count($line); $i++){
            $code.= $line[$i];
        }
        file_put_contents($file, $code);
    }

    private function _add()
    {
        if(($mk = $this->getMasterKey()) === false){
            echo "Bad key :(\n";
            return 1;
        }
        $service = strtolower(trim(readline('Service: ')));
        if(strlen($service) === 0){
            $service = 'n/a';
        }                
        if(isset($this->db->accounts)){
            $accounts = json_decode(openssl_decrypt($this->db->accounts, self::CIPHER, $mk, 0, $this->db->iv));
        } else {
            $accounts = [];
        }
        foreach($accounts as $a){
            if($a[0] === $service){
                echo "Service \"$service\" already registered\n";
                echo "Find a unique name or delete it.\n";
                return 1;                
            }
        }
        $email = strtolower(trim(readline('E-mail: ')));
        if(strlen($email) === 0){
            $email = 'n/a';
        }        
        $username = trim(readline('Username: '));
        if(strlen($username) === 0){
            $username = 'n/a';
        }
        $password = trim(readline('Password (leave blank to generate): '));
        if(strlen($password) === 0){
            $password = $this->genPassword();
        }        
        $accounts[] = [$service, $email, $username, $password];
        $this->db->iv = $this->genIV();
        $this->db->accounts = openssl_encrypt(json_encode($accounts), self::CIPHER, $mk, 0, $this->db->iv);
        $this->save();
        echo "New service successfully added\n";
        echo "  Name      $service\n";
        echo "  E-mail    $email\n";
        echo "  Username  $username\n";
        echo "  Password  $password\n";
        return 0;
    }

    private function _delete($service = '')
    {
        $service = strtolower(trim($service));
        if($service === ''){
            echo "$this->name delete: please provide a SERVICE name\n";
            return 1;                        
        }        
        if(($mk = $this->getMasterKey()) === false){
            echo "Bad key :(\n";
            return 1;
        }
        if(isset($this->db->accounts)){
            $accounts = json_decode(openssl_decrypt($this->db->accounts, self::CIPHER, $mk, 0, $this->db->iv));
        } else {
            $accounts = [];
        }
        $accounts_new = [];
        foreach($accounts as $a){
            if($a[0] !== $service){
                $accounts_new[] = $a;
            }
        }
        if(count($accounts) === count($accounts_new)){
            echo "$this->name delete: service \"$service\" not found\n";
            return 1;            
        }
        $this->db->iv = $this->genIV();
        $this->db->accounts = openssl_encrypt(json_encode($accounts_new), self::CIPHER, $mk, 0, $this->db->iv);
        $this->save();
        return 0;
    }

    private function  _savemk($arg = '')
    {
        $validargs = ['-r', '--ram', '-h', '--home'];
        if(($opt = array_search($arg, $validargs)) === false){
            echo "$this->name: use --ram or --home option\n";
            return 1;
        }

        $file = ($opt < 2) ? $this->mkrfile : $this->mkhfile;

        if(($mk = $this->getMasterKey()) === false){
            echo "Bad key :(\n";
            return 1;
        }

        if(@file_put_contents($file, $mk."\n") === false){
            echo "$this->name: error while writing on \"$file\"\n";
            return 2;
        }

        chmod($file, self::PERMISSIONS_MK);
        echo "Master key saved in $file\n";
        return 0;       
    }
    
    private function _show($keyword = '')
    {
        $keyword = trim(strtolower($keyword));
        
        if(!isset($this->db->accounts)){
            echo "Account list is empty\n";
            return 0;
        }
        if(($mk = $this->getMasterKey()) === false){
            echo "Bad key :(\n";
            return 1;
        }
        
        $accounts = json_decode(openssl_decrypt($this->db->accounts, self::CIPHER, $mk, 0, $this->db->iv));

        if(!count($accounts)){
            echo "Account list is empty\n";
            return 0;
        }
        
        if($keyword !== ''){
            $filtered = [];
            foreach($accounts as $a){
                if(strpos($a[0], $keyword) !== false){
                    $filtered[] = $a;
                }
            }
            $accounts = $filtered;
        }

        if(!count($accounts)){
            echo "\"$keyword\": no matches\n";
            return 1;
        }
        
        $len = $this->getColsLen($accounts);
        foreach($this->cols as $k => $c){
            echo str_pad($c, $len[$k]);
        }
        echo "\n" . str_repeat('-', array_sum($len)) . "\n";
        foreach($accounts as $a){
            foreach([0,1,2,3] as $k){
                echo str_pad($a[$k], $len[$k]);
            }
            echo "\n";
        }
    }

    private function _dump($opt = '')
    {
        $db = $this->db;
        
        if($opt === '--decrypt'){
            if(($mk = $this->getMasterKey()) === false){
                echo "Bad key :(\n";
                return 1;
            }
            if(!isset($db->accounts)){
                $db->accounts = [];
            } else {
                $db->accounts = json_decode(openssl_decrypt($this->db->accounts, self::CIPHER, $mk, 0, $this->db->iv));
            }            
        }

        echo json_encode($this->db, JSON_PRETTY_PRINT);
        echo "\n";
        return 0;        
    }

    private function _genpasswd()
    {
        echo $this->genPassword(...func_get_args()) . "\n";
        return 0;
    }
    
    private function _help()
    {
        echo "safepass: php command line password manager.\n";
        echo "Accounts data is encrypted in source code itself.\n\n";
        echo "USAGE\n";
        echo "  $this->name COMMAND [OPTION]\n\n";
        echo "COMMANDS\n";
        echo "  add                add new account\n";
        echo "  delete SERVICE     delete account by SERVICE name\n";
        echo "  dump [--decrypt]   display database as json\n";
        echo "  genpasswd          display a random generated password\n";        
        echo "  help               display this help and exit\n";
        echo "  reset              erase database, reinit\n";
        echo "  savemk LOCATION    save master key on disk or ram for future use\n";
        echo "  show [KEYWORD]     display accounts that match KEYWORD\n";
        echo "  version            output version information and exit\n\n";
        echo "SAVEMK USAGE\n";
        echo "  $this->name savemk [-r|-h]\n\n";        
        echo "  -r, --ram          save masterkey temporarly in ram\n";
        echo "                     mk will be in $this->mkrfile\n";            
        echo "  -h, --home         save masterkey permanently\n";
        echo "                     mk will be in $this->mkhfile\n\n";
        echo "GENPASSWD USAGE\n";
        echo "  $this->name genpasswd [len] [lc] [uc] [spec] [num]\n\n";
        echo "  len                password length (default=" . self::PASS_RULES[0] . ")\n";
        echo "  lc                 minimum lowercase chars (default=" . self::PASS_RULES[1] . ")\n";
        echo "  uc                 exact uppercase chars (default=" . self::PASS_RULES[2] . ")\n";
        echo "  spec               exact special chars (default=" . self::PASS_RULES[3] . ")\n";
        echo "  num                exact numerical chars (default=" . self::PASS_RULES[4] . ")\n";        
        return 0;
    }
    
    private function _reset()
    {
        $confirm = strtolower(trim(readline("$this->name: erase all data [y/n] ? ")));
        if($confirm !== 'y'){
            echo "$this->name reset: operation aborted\n";
            return 1;            
        }
        if(is_file($this->mkrfile)){
            @unlink($this->mkrfile);
        }
        if(is_file($this->mkhfile)){
            @unlink($this->mkhfile);
        }
        $this->db = new StdClass;
        $this->db->iv = $this->genIV();
        $this->save();
        chmod(__FILE__, self::PERMISSIONS);       
    }
    
    private function _version()
    {
        echo "safepass " . self::VERSION . "\n";
        echo "location: " . __FILE__ . "\n";
        return 0;
    }
}
