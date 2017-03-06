<?php

/**
 * API-call related functions
 * @author marinu666
 * @license MIT License - https://github.com/marinu666/PHP-btce-api
 */
class Btce
{
    const DIRECTION_BUY = 'buy';
    const DIRECTION_SELL = 'sell';
    const ORDER_DESC = 'DESC';
    const ORDER_ASC = 'ASC';

    const PAIR_BTC_USD = 'btc_usd';
    const PAIR_BTC_RUR = 'btc_rur';
    const PAIR_BTC_EUR = 'btc_eur';

    const PAIR_LTC_BTC = 'ltc_btc';
    const PAIR_LTC_USD = 'ltc_usd';
    const PAIR_LTC_RUR = 'ltc_rur';
    const PAIR_LTC_EUR = 'ltc_eur';

    const PAIR_NMC_BTC = 'nmc_btc';
    const PAIR_NMC_USD = 'nmc_usd';

    const PAIR_NVC_BTC = 'nvc_btc';
    const PAIR_NVC_USD = 'nvc_usd';

    const PAIR_USD_RUR = 'usd_rur';
    const PAIR_EUR_USD = 'eur_usd';
    const PAIR_EUR_RUR = 'eur_rur';

    const PAIR_PPC_BTC = 'ppc_btc';
    const PAIR_PPC_USD = 'ppc_usd';

    const PAIR_DSH_BTC = 'dsh_btc';
    const PAIR_DSH_USD = 'dsh_usd';

    const PAIR_ETH_BTC = 'eth_btc';
    const PAIR_ETH_USD = 'eth_usd';
    const PAIR_ETH_EUR = 'eth_eur';
    const PAIR_ETH_LTC = 'eth_ltc';
    const PAIR_ETH_RUR = 'eth_rur';

    const PAIR_TRC_BTC = 'trc_btc';
    const PAIR_FTC_BTC = 'ftc_btc';
    const PAIR_XPM_BTC = 'xpm_btc';

    protected $public_api = 'https://btc-e.nz/api/3/';

    protected $api_key;
    protected $api_secret;
    protected $nonce;
    protected $RETRY_FLAG = false;

    public function __construct($api_key, $api_secret, $baseNonce = false)
    {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        if ($baseNonce === false) {
            // Try 1?
            $this->nonce = time();
        } else {
            $this->nonce = $baseNonce;
        }
    }

    /**
     * Get the nonce
     * @return array
     */
    protected function getNonce()
    {
        $this->nonce++;

        return [0.05, $this->nonce];
    }

    /**
     * Call the API
     *
     * @param string $method
     * @param array $req
     *
     * @return array
     * @throws Exception
     */
    public function query($method, $req = [])
    {
        $req['method'] = $method;
        $mt = $this->getNonce();
        $req['nonce'] = $mt[1];

        // generate the POST data string
        $post_data = http_build_query($req, '', '&');

        // Generate the keyed hash value to post
        $sign = hash_hmac("sha512", $post_data, $this->api_secret);

        // Add to the headers
        $headers = [
            'Sign: ' . $sign,
            'Key: ' . $this->api_key,
        ];

        // Create a CURL Handler for use
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,
            'Mozilla/4.0 (compatible; Marinu666 BTCE PHP client; ' . php_uname('s') . '; PHP/' . phpversion() . ')');
        curl_setopt($ch, CURLOPT_URL, 'https://btc-e.nz/tapi/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Send API Request
        $res = curl_exec($ch);

        // Check for failure & Clean-up curl handler
        if ($res === false) {
            $e = curl_error($ch);
            curl_close($ch);
            throw new BTCeAPIFailureException('Could not get reply: ' . $e);
        }
        curl_close($ch);

        // Decode the JSON
        $result = json_decode($res, true);
        // is it valid JSON?
        if (!$result) {
            throw new BTCeAPIInvalidJSONException('Invalid data received, please make sure connection is working and requested API exists');
        }

        // Recover from an incorrect noonce
        if (isset($result['error']) === true) {
            if (strpos($result['error'], 'nonce') <= -1 || $this->RETRY_FLAG !== false) {
                throw new BTCeAPIErrorException('API Error Message: ' . $result['error'] . ". Response: "
                    . print_r($result, true));
            }
            $matches = [];
            preg_match('/:([0-9]+),/', $result['error'], $matches);
            $this->RETRY_FLAG = true;
            trigger_error("Nonce we sent ({$this->nonce}) is invalid, retrying request with server returned nonce: ({$matches[1]})!");
            $this->nonce = $matches[1];

            return $this->query($method, $req);
        }
        // Cool -> Return
        $this->RETRY_FLAG = false;

        return $result;
    }

    /**
     * Retrieve some JSON
     *
     * @param string $URL
     *
     * @return array
     */
    protected function retrieveJSON($URL)
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
            ],
        ];
        $context = stream_context_create($opts);
        $feed = file_get_contents($URL, false, $context);
        $json = json_decode($feed, true);

        return $json;
    }

    /**
     * Place an order
     *
     * @param float $amount
     * @param string $pair
     * @param string $direction
     * @param float $price
     *
     * @throws BTCeAPIInvalidParameterException
     * @return array
     */
    public function makeOrder($amount, $pair, $direction, $price)
    {
        if ($direction != self::DIRECTION_BUY && $direction != self::DIRECTION_SELL) {
            throw new BTCeAPIInvalidParameterException('Expected constant from ' . __CLASS__ . '::DIRECTION_BUY or '
                . __CLASS__ . '::DIRECTION_SELL. Found: ' . $direction);
        }
        $data = $this->query("Trade", [
            'pair' => $pair,
            'type' => $direction,
            'rate' => $price,
            'amount' => $amount,
        ]);

        return $data;
    }

    public function getInfo()
    {
        $data = $this->query("getInfo");

        return $data;
    }

    public function getInfoData()
    {
        $info = $this->getInfo();
        if ($info['success'] !== 1) {
            throw new BTCeAPIFailureException($info['error']);
        }

        return $info['return'];
    }

    public function transHistory(
        $offset = 0, $count = 1000, $fromId = 0, $endId = null, $order = self::ORDER_DESC, $sinceUt = 0, $endUt = null
    ) {
        $params = [
            'from' => $offset,
            'count' => $count,
            'from_id' => $fromId,
            'end_id' => $endId,
            'order' => $order,
            'since' => $sinceUt,
            'end' => $endUt,
        ];
        foreach ($params as $k => $v) {
            if (null === $v) {
                unset($params[$k]);
            }
        }

        $data = $this->query("TransHistory", $params);

        return $data;
    }

    public function tradeHistory(
        $offset = 0, $count = 1000, $fromId = 0, $endId = null, $order = self::ORDER_DESC, $sinceUt = 0, $endUt = null,
        $pair = null
    ) {
        $params = [
            'from' => $offset,
            'count' => $count,
            'from_id' => $fromId,
            'end_id' => $endId,
            'order' => $order,
            'since' => $sinceUt,
            'end' => $endUt,
            'pair' => $pair,
        ];
        foreach ($params as $k => $v) {
            if (null === $v) {
                unset($params[$k]);
            }
        }

        $data = $this->query("TradeHistory", $params);

        return $data;
    }

    public function activeOrders($pair = null)
    {
        $params = [
            'pair' => $pair,
        ];
        foreach ($params as $k => $v) {
            if (null === $v) {
                unset($params[$k]);
            }
        }

        $data = $this->query("ActiveOrders", $params);

        return $data;
    }

    public function cancelOrder($orderId)
    {
        $params = [
            'order_id' => $orderId,
        ];

        $data = $this->query("CancelOrder", $params);

        return $data;
    }

    /**
     * Check an order that is complete (non-active)
     *
     * @param int $orderID
     *
     * @return array
     * @throws Exception
     */
    public function checkPastOrder($orderID)
    {
        $data = $this->query("OrderList", [
            'from_id' => $orderID,
            'to_id' => $orderID,
            /*'count' => 15,*/
            'active' => 0,
        ]);
        if ($data['success'] == "0") {
            throw new BTCeAPIErrorException("Error: " . $data['error']);
        }

        return $data;
    }

    /**
     * Public API: Retrieve the Fee for a currency pair
     *
     * @param string $pair
     *
     * @return array
     */
    public function getPairFee($pair)
    {
        return $this->retrieveJSON($this->public_api . "fee/" . $pair);
    }

    /**
     * Public API: Retrieve the Ticker for a currency pair
     *
     * @param string $pair
     *
     * @return array
     */
    public function getPairTicker($pair)
    {
        return $this->retrieveJSON($this->public_api . "ticker/" . $pair);
    }

    /**
     * Public API: Retrieve the Trades for a currency pair
     *
     * @param string $pair
     * @param int $limit
     *
     * @return array
     */
    public function getPairTrades($pair, $limit = 150)
    {
        if ($limit > 2000 || $limit < 1) {
            throw new InvalidArgumentException();
        }

        return $this->retrieveJSON($this->public_api . "trades/" . $pair . "?limit=" . $limit);
    }

    /**
     * Public API: Retrieve the Depth for a currency pair
     *
     * @param string $pair
     * @param int $limit
     *
     * @return array
     */
    public function getPairDepth($pair, $limit = 150)
    {
        if ($limit > 2000 || $limit < 1) {
            throw new InvalidArgumentException();
        }

        return $this->retrieveJSON($this->public_api . "depth/" . $pair . "?limit=" . $limit);
    }

}

/**
 * Exceptions
 */
class BTCeAPIException extends Exception
{
}

class BTCeAPIFailureException extends BTCeAPIException
{
}

class BTCeAPIInvalidJSONException extends BTCeAPIException
{
}

class BTCeAPIErrorException extends BTCeAPIException
{
}

class BTCeAPIInvalidParameterException extends BTCeAPIException
{
}
