<?php
/**
 * ExportJobRepository.php
 * Copyright (C) 2016 thegrumpydictator@gmail.com
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

declare(strict_types = 1);

namespace FireflyIII\Repositories\ExportJob;

use Carbon\Carbon;
use FireflyIII\Models\ExportJob;
use FireflyIII\User;
use Illuminate\Support\Str;

/**
 * Class ExportJobRepository
 *
 * @package FireflyIII\Repositories\ExportJob
 */
class ExportJobRepository implements ExportJobRepositoryInterface
{
    /** @var User */
    private $user;

    /**
     * ExportJobRepository constructor.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * @return bool
     */
    public function cleanup(): bool
    {
        $dayAgo = Carbon::create()->subDay();
        $set    = ExportJob::where('created_at', '<', $dayAgo->format('Y-m-d H:i:s'))
                           ->whereIn('status', ['never_started', 'export_status_finished', 'export_downloaded'])
                           ->get();

        // loop set:
        /** @var ExportJob $entry */
        foreach ($set as $entry) {
            $key   = $entry->key;
            $len   = strlen($key);
            $files = scandir(storage_path('export'));
            /** @var string $file */
            foreach ($files as $file) {
                if (substr($file, 0, $len) === $key) {
                    unlink(storage_path('export') . DIRECTORY_SEPARATOR . $file);
                }
            }
            $entry->delete();
        }

        return true;
    }

    /**
     * @return ExportJob
     */
    public function create(): ExportJob
    {
        $count = 0;
        while ($count < 30) {
            $key      = Str::random(12);
            $existing = $this->findByKey($key);
            if (is_null($existing->id)) {
                $exportJob = new ExportJob;
                $exportJob->user()->associate($this->user);
                $exportJob->key    = Str::random(12);
                $exportJob->status = 'export_status_never_started';
                $exportJob->save();

                // breaks the loop:

                return $exportJob;
            }
            $count++;

        }

        return new ExportJob;

    }

    /**
     * @param string $key
     *
     * @return ExportJob|null
     */
    public function findByKey(string $key): ExportJob
    {
        $result = $this->user->exportJobs()->where('key', $key)->first(['export_jobs.*']);
        if (is_null($result)) {
            return new ExportJob;
        }

        return $result;
    }

}
