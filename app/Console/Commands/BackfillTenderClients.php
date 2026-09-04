<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Tender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off M10 migration helper: groups existing tenders by their distinct
 * contracting_authority string, creates one Client per distinct value (or reuses an
 * existing one with the same name, so the command is safe to re-run), and links every
 * matching tender's client_id. contracting_authority itself is left untouched — client_id is
 * an additional link, not a replacement (see [[milestones]]'s m10 file). Run manually, not
 * part of DatabaseSeeder.
 */
#[Signature('tenders:backfill-clients')]
#[Description('Creates a Client per distinct contracting_authority value and links matching tenders.')]
class BackfillTenderClients extends Command
{
    public function handle(): int
    {
        $authorities = Tender::query()
            ->whereNull('client_id')
            ->distinct()
            ->pluck('contracting_authority');

        $linked = 0;

        foreach ($authorities as $authority) {
            $client = Client::query()->firstOrCreate(['name' => $authority]);

            $linked += Tender::query()
                ->where('contracting_authority', $authority)
                ->whereNull('client_id')
                ->update(['client_id' => $client->id]);
        }

        $this->info("Linked {$linked} tender(s) across {$authorities->count()} client(s).");

        return self::SUCCESS;
    }
}
