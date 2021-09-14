# eveAppBase
Solution to make requests to EVE Online API https://esi.evetech.net/ui/


## Sample of use 


### Auth class implementation
```php
<?php

namespace eve;

include_once(dirname(__FILE__) . '/EveAuthBase.php');

/**
 * Just simple case to use
 *
 * @author I.V.Zhiradkov
 */
class EveAuth extends EveAuthBase {

    /**
     * what you gonna do in fail auth case
     */
    public function authFailed() {
        header("Location: " . 'https://login.eveonline.com/v2/oauth/authorize/?response_type=code&redirect_uri=' . $this->redirect . '&client_id=' . $this->clientId . '&scope=' . urlencode($this->scope) . '&state=' . $this->state);
    }

    /**
     * where you gonna save auth state
     * in this case it's COOKIE solution
     */
    public function storeState() {
        foreach ($this->objVars as $var) {
            if (empty($_COOKIE[$var])) {
                setcookie($var, $this->$var, time() + (3600 * 24 * 30), '/');
            }
            $data[$var] = $this->$var;
        }
    }

    /**
     * where is auth state stored and how to read it
     * in this case it's COOKIE solution
     */
    public function readState() {
        $restored = true;
        foreach ($this->objVars as $var) {
            if (!empty($_COOKIE[$var])) {
                $this->$var = $_COOKIE[$var];
            } else {
                $restored = false;
            }
        }
        return $restored;
    }

    /**
     * just sample how to read character into from EVE
     */
    public function getCharacterInfo() {
        return $this->getDataSimpleGetRequest($this->baseUrl . 'characters/{CHARACTERID}/?datasource=tranquility&token={ACCESSTOKEN}', $this->defaultPatterns);
    }

}
```

### Request implementation
```php
<?php

/**
 * just sample of use
 */

include_once(dirname( __FILE__ ) . '/EveAuth.php');

$eveAuth = new eve\EveAuth();
print_r($eveAuth->getCharacterInfo());

```

### Auth callback implementation
In this case it's stored in ./calback folder without URL rewriting
```php
<?php
namespace eve;

/**
 * just sample of use
 */

include_once(dirname(__FILE__) . '/../EveAuth.php');

$model = new EveAuth();

```
