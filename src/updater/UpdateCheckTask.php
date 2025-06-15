<?php

declare(strict_types=1);

namespace pocketmine\updater;

use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Internet;
use function is_array;
use function is_string;
use function json_decode;

class UpdateCheckTask extends AsyncTask{
    private const TLS_KEY_UPDATER = "updater";

    private string $error = "Unknown error";

    public function __construct(
        UpdateChecker $updater,
        private string $endpoint,
        private string $channel
    ){
        $this->storeLocal(self::TLS_KEY_UPDATER, $updater);
    }

    public function onRun() : void {
        $error = "";
        $url = sprintf("%s?channel=%s", $this->endpoint, $this->channel);

        $response = Internet::getURL($url, 4, [], $error);
        $this->error = $error;

        if ($response === null) {
            return;
        }

        $data = json_decode($response->getBody(), true);
        if (!is_array($data)) {
            $this->error = "Invalid response data";
            return;
        }

        if (isset($data["error"]) && is_string($data["error"])) {
            $this->error = $data["error"];
            return;
        }

        static $mapper = null;
        if ($mapper === null) {
            $mapper = new \JsonMapper();
            $mapper->bExceptionOnMissingData = false; // avoid exceptions on missing data for speed
            $mapper->bStrictObjectTypeChecking = true;
            $mapper->bEnforceMapType = false;
        }

        try {
            /** @var UpdateInfo $responseObj */
            $responseObj = $mapper->map($data, new UpdateInfo());
            $this->setResult($responseObj);
        } catch (\JsonMapper_Exception $e) {
            $this->error = "Invalid JSON response data: " . $e->getMessage();
        }
    }

    public function onCompletion() : void {
        /** @var UpdateChecker $updater */
        $updater = $this->fetchLocal(self::TLS_KEY_UPDATER);
        if ($this->hasResult()) {
            /** @var UpdateInfo $response */
            $response = $this->getResult();
            $updater->checkUpdateCallback($response);
        } else {
            $updater->checkUpdateError($this->error);
        }
    }
}
