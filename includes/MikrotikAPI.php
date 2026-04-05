<?php
/**
 * Mikrotik RouterOS API Client
 * 
 * Komunikasi dengan Mikrotik via API port 8728
 * Kredensial diambil dari environment variable (aman)
 */

class MikrotikAPI 
{
    private $socket = null;
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private bool $connected = false;
    private int $timeout = 5;
    
    public function __construct() 
    {
        // Ambil kredensial dari environment (BUKAN hardcode!)
        $this->host = getenv('MIKROTIK_HOST') ?: '172.16.1.1';
        $this->port = (int)(getenv('MIKROTIK_PORT') ?: 8728);
        $this->user = getenv('MIKROTIK_USER') ?: '';
        $this->pass = getenv('MIKROTIK_PASS') ?: '';
        
        if (empty($this->user)) {
            throw new Exception('MIKROTIK_USER not configured in environment');
        }
    }
    
    /**
     * Connect ke Mikrotik
     */
    public function connect(): bool 
    {
        $this->socket = @fsockopen(
            $this->host, 
            $this->port, 
            $errno, 
            $errstr, 
            $this->timeout
        );
        
        if (!$this->socket) {
            error_log("Mikrotik connection failed: [$errno] $errstr");
            return false;
        }
        
        // Set socket timeout
        stream_set_timeout($this->socket, $this->timeout);
        
        // Attempt login
        return $this->login();
    }
    
    /**
     * Login ke RouterOS API
     */
    private function login(): bool 
    {
        // Send login command (RouterOS v6.43+)
        $this->write('/login', false);
        $this->write('=name=' . $this->user, false);
        $this->write('=password=' . $this->pass);
        
        $response = $this->read();
        
        if (isset($response[0]) && $response[0] === '!done') {
            $this->connected = true;
            return true;
        }
        
        // Legacy login untuk RouterOS < 6.43
        if (isset($response[1]) && strpos($response[1], '=ret=') === 0) {
            $challenge = substr($response[1], 5);
            $this->write('/login', false);
            $this->write('=name=' . $this->user, false);
            $this->write('=response=00' . md5(chr(0) . $this->pass . pack('H*', $challenge)));
            
            $response = $this->read();
            if (isset($response[0]) && $response[0] === '!done') {
                $this->connected = true;
                return true;
            }
        }
        
        error_log("Mikrotik login failed: " . json_encode($response));
        return false;
    }
    
    /**
     * Write command ke socket
     */
    private function write(string $command, bool $end = true): void 
    {
        $length = strlen($command);
        
        if ($length < 0x80) {
            fwrite($this->socket, chr($length));
        } elseif ($length < 0x4000) {
            fwrite($this->socket, chr(($length >> 8) | 0x80) . chr($length & 0xFF));
        } elseif ($length < 0x200000) {
            fwrite($this->socket, chr(($length >> 16) | 0xC0) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF));
        }
        
        fwrite($this->socket, $command);
        
        if ($end) {
            fwrite($this->socket, chr(0)); // End of command
        }
    }
    
    /**
     * Read response dari socket
     */
    private function read(): array 
    {
        $response = [];
        $done = false;
        
        // Set timeout for reading
        stream_set_timeout($this->socket, 3);
        
        while (!$done) {
            // Read length byte
            $byte = @fread($this->socket, 1);
            
            // Check for timeout or error
            $info = stream_get_meta_data($this->socket);
            if ($info['timed_out'] || $byte === false || $byte === '') {
                break;
            }
            
            $byte = ord($byte);
            
            if ($byte === 0) {
                // End of sentence
                continue;
            }
            
            // Calculate word length
            if ($byte < 0x80) {
                $length = $byte;
            } elseif ($byte < 0xC0) {
                $length = (($byte & 0x3F) << 8) + ord(fread($this->socket, 1));
            } elseif ($byte < 0xE0) {
                $length = (($byte & 0x1F) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
            } elseif ($byte < 0xF0) {
                $length = (($byte & 0x0F) << 24) + (ord(fread($this->socket, 1)) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
            } else {
                $length = ord(fread($this->socket, 1)) << 24;
                $length += ord(fread($this->socket, 1)) << 16;
                $length += ord(fread($this->socket, 1)) << 8;
                $length += ord(fread($this->socket, 1));
            }
            
            if ($length > 0) {
                $word = '';
                while (strlen($word) < $length) {
                    $word .= fread($this->socket, $length - strlen($word));
                }
                $response[] = $word;
                
                // Check for end markers
                if ($word === '!done' || $word === '!trap' || $word === '!fatal') {
                    $done = true;
                }
            }
        }
        
        return $response;
    }
    
    /**
     * Tambah user hotspot
     * 
     * @param string $username
     * @param string $password  
     * @param string $profile Nama profile di Mikrotik
     * @param string $limitUptime Format Mikrotik: "1d 00:00:00" atau "7d 00:00:00"
     */
    public function addHotspotUser(string $username, string $password, string $profile, string $limitUptime = ''): bool 
    {
        if (!$this->connected && !$this->connect()) {
            return false;
        }
        
        $this->write('/ip/hotspot/user/add', false);
        $this->write('=name=' . $username, false);
        $this->write('=password=' . $password, false);
        $this->write('=profile=' . $profile, false);
        $this->write('=server=hotspot1', false);
        
        // Tambahkan limit-uptime jika ada
        if (!empty($limitUptime)) {
            $this->write('=limit-uptime=' . $limitUptime);
        } else {
            $this->write('');  // End command
        }
        // Tidak ada comment agar tidak konflik dengan Mikhmon
        
        $response = $this->read();
        
        // Check if successful
        if (isset($response[0]) && $response[0] === '!done') {
            return true;
        }
        
        // Log detailed error
        $errorMsg = "Mikrotik add user failed: " . json_encode($response);
        error_log($errorMsg);
        
        // Also output to browser for debugging
        echo "\n[DEBUG] Mikrotik Response: " . json_encode($response) . "\n";
        
        return false;
    }
    
    /**
     * Cek apakah profile ada di Mikrotik
     */
    public function profileExists(string $profile): bool 
    {
        if (!$this->connected && !$this->connect()) {
            return false;
        }
        
        $this->write('/ip/hotspot/user/profile/print', false);
        $this->write('?name=' . $profile);
        
        $response = $this->read();
        
        // Jika ada data selain !done, berarti profile ada
        return count($response) > 1 || (isset($response[1]) && strpos($response[1], '=name=') !== false);
    }
    
    /**
     * Disconnect dari Mikrotik
     */
    public function disconnect(): void 
    {
        if ($this->socket) {
            $this->write('/quit');
            fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
        }
    }
    
    /**
     * Destructor - pastikan koneksi ditutup
     */
    public function __destruct() 
    {
        $this->disconnect();
    }
}
