<?php

/**
 * Utility functions for remote data retrieval.
 */
/**
 * Class for TEA encryption/decryption
 */
class Tea
{
	private static function _long2str($v, $w)
	{
		$len = count($v);
		$s = [];
		for ($i = 0; $i < $len; $i++)
		{
			$s[$i] = pack("V", $v[$i]);
		}
		if ($w) {
			return substr(join('', $s), 0, $v[$len - 1]);
		}	else {
			return join('', $s);
		}
	}
	private static function _str2long($s, $w)
	{
		$v = unpack("V*", $s. str_repeat("\0", (4 - strlen($s) % 4) & 3));
		$v = array_values($v);
		if ($w) {
			$v[count($v)] = strlen($s);
		}
		return $v;
	}

	// Encrypt
	public static function encrypt($str, $key = "")
	{
		if ($str == "")
			return "";
		$key = $key ?: Config("RANDOM_KEY");
		$v = self::_str2long($str, true);
		$k = self::_str2long($key, false);
		$cntk = count($k);
		if ($cntk < 4) {
			for ($i = $cntk; $i < 4; $i++) {
				$k[$i] = 0;
			}
		}
		$n = count($v) - 1;
		$z = $v[$n];
		$y = $v[0];
		$delta = 0x9E3779B9;
		$q = floor(6 + 52 / ($n + 1));
		$sum = 0;
		while (0 < $q--) {
			$sum = self::_int32($sum + $delta);
			$e = $sum >> 2 & 3;
			for ($p = 0; $p < $n; $p++) {
				$y = $v[$p + 1];
				$mx = self::_int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ self::_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
				$z = $v[$p] = self::_int32($v[$p] + $mx);
			}
			$y = $v[0];
			$mx = self::_int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ self::_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
			$z = $v[$n] = self::_int32($v[$n] + $mx);
		}
		return self::_urlEncode(self::_long2str($v, false));
	}

	// Decrypt
	public static function decrypt($str, $key = "")
	{
		$str = self::_urlDecode($str);
		if ($str == "")
			return "";
		$key = $key ?: Config("RANDOM_KEY");
		$v = self::_str2long($str, false);
		$k = self::_str2long($key, false);
		$cntk = count($k);
		if ($cntk < 4) {
			for ($i = $cntk; $i < 4; $i++) {
				$k[$i] = 0;
			}
		}
		$n = count($v) - 1;
		$z = $v[$n];
		$y = $v[0];
		$delta = 0x9E3779B9;
		$q = floor(6 + 52 / ($n + 1));
		$sum = self::_int32($q * $delta);
		while ($sum != 0) {
			$e = $sum >> 2 & 3;
			for ($p = $n; $p > 0; $p--) {
				$z = $v[$p - 1];
				$mx = self::_int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ self::_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
				$y = $v[$p] = self::_int32($v[$p] - $mx);
			}
			$z = $v[$n];
			$mx = self::_int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ self::_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
			$y = $v[0] = self::_int32($v[0] - $mx);
			$sum = self::_int32($sum - $delta);
		}
		return self::_long2str($v, true);
	}
	private static function _int32($n)
	{
		while ($n >= 2147483648) $n -= 4294967296;
		while ($n <= -2147483649) $n += 4294967296;
		return (int)$n;
	}
	private static function _urlEncode($string)
	{
		$data = base64_encode($string);
		return str_replace(['+', '/', '='], ['-', '_', '.'], $data);
	}
	private static function _urlDecode($string)
	{
		$data = str_replace(['-', '_', '.'], ['+', '/', '='], $string);
		return base64_decode($data);
	}
}

// Encrypt
function Encrypt($str, $key = "") {
	return Tea::encrypt($str, $key ?: Config("RANDOM_KEY"));
}

class Utility
{
    public function getDati(string $SQLinv): array
    {
        $token = Encrypt(microtime(true) . random_int(1000, 9999), 'test');
        $sqlEncoded = Encrypt(htmlspecialchars_decode($SQLinv), 'test');
        
        $queryParams = http_build_query([
            'action' => 'execute_query',
            'token'  => $token,
            'SQL'    => $sqlEncoded,
        ]);
        
        $url = 'https://new.cosulich.it/approvazione_fatture/user_get_inaz_json.php?' . $queryParams;
        
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'allow_self_signed'=> true,
            ],
            'http' => [
                'method'  => 'GET',
                'timeout' => 15,
            ],
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            return [];
        }
        
        $data = json_decode($response, true);
        
        return is_array($data) ? $data : [];
    }

    /**
     * Build a query replacing placeholders with safely escaped parameters.
     *
     * @param string $sql   Query containing `?` placeholders.
     * @param array  $params Parameters to bind in order.
     * @param string $types  Type string ("s" for string, "i" for int, "d" for double).
     */
    public function prepareQuery(string $sql, array $params, string $types = ''): string
    {
        if ($types === '') {
            $types = str_repeat('s', count($params));
        }

        $escaped = [];
        foreach ($params as $index => $param) {
            $type = $types[$index] ?? 's';
            switch ($type) {
                case 'i':
                    $escaped[] = (string) (int) $param;
                    break;
                case 'd':
                    $escaped[] = (string) (float) $param;
                    break;
                default:
                    $escaped[] = "'" . addslashes((string) $param) . "'";
                    break;
            }
        }

        $i = 0;
        return preg_replace_callback('/\?/', function () use (&$i, $escaped) {
            return $escaped[$i++] ?? '?';
        }, $sql);
    }

    /**
     * Execute a query with parameters.
     */
    public function getDatiPrepared(string $sql, array $params, string $types = ''): array
    {
        $query = $this->prepareQuery($sql, $params, $types);
        return $this->getDati($query);
    }

    /**
     * Escape special characters used in SQL LIKE clauses.
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, "_%");
    }
}

/**
 * Calcola la differenza in mesi interi tra due date.
 * Sottrae un mese se il giorno del secondo argomento è inferiore al giorno del primo.
 * Le date devono essere nel formato 'Y-m-d'.
 */
function diff_mesi(?string $from, ?string $to): ?int
{
    if (!$from || !$to) {
        return null;
    }
    $tz = new DateTimeZone('Europe/Rome');
    $fromDate = new DateTime($from, $tz);
    $toDate = new DateTime($to, $tz);
    $months = ($toDate->format('Y') - $fromDate->format('Y')) * 12 + ($toDate->format('n') - $fromDate->format('n'));
    if ((int)$toDate->format('j') < (int)$fromDate->format('j')) {
        $months--;
    }
    return $months;
}
