<?php

use App\Models\Tender;
use App\Models\TenderDocument;
use App\Models\TenderDocumentVersion;
use App\Models\TenderTeamMember;
use App\Models\User;

describe('versions', function () {
    it('orders versions by version_number descending', function () {
        $document = TenderDocument::factory()->create();
        $first = TenderDocumentVersion::factory()->create(['tender_document_id' => $document->id, 'version_number' => 1]);
        $latest = TenderDocumentVersion::factory()->create(['tender_document_id' => $document->id, 'version_number' => 2]);

        expect($document->versions()->pluck('id')->all())->toBe([$latest->id, $first->id]);
    });

    it('exposes the highest version_number via currentVersion', function () {
        $document = TenderDocument::factory()->create();
        TenderDocumentVersion::factory()->create(['tender_document_id' => $document->id, 'version_number' => 1]);
        $latest = TenderDocumentVersion::factory()->create(['tender_document_id' => $document->id, 'version_number' => 2]);

        expect($document->currentVersion()->first()->id)->toBe($latest->id);
    });
});

describe('locking', function () {
    it('is not locked by default', function () {
        $document = TenderDocument::factory()->create();

        expect($document->isLocked())->toBeFalse();
    });

    it('stamps locked_at and locked_by via lock()', function () {
        $document = TenderDocument::factory()->create();
        $actor = User::factory()->create();

        $document->lock($actor);

        expect($document->fresh()->isLocked())->toBeTrue();
        expect($document->fresh()->locked_by)->toBe($actor->id);
    });
});

describe('cascade delete', function () {
    it('deletes a tender\'s documents when the tender is hard-deleted', function () {
        $tender = Tender::factory()->create();
        $document = TenderDocument::factory()->create(['tender_id' => $tender->id]);

        $tender->hardDelete(User::factory()->create(), 'Genuine test junk entry');

        expect(TenderDocument::find($document->id))->toBeNull();
    });

    it('deletes a document\'s versions when the document is deleted', function () {
        $document = TenderDocument::factory()->create();
        $version = TenderDocumentVersion::factory()->create(['tender_document_id' => $document->id]);

        $document->delete();

        expect(TenderDocumentVersion::find($version->id))->toBeNull();
    });
});

describe('linkedToDocuments', function () {
    it('is true for the tender owner', function () {
        $owner = User::factory()->create();
        $tender = Tender::factory()->create(['owner_id' => $owner->id]);

        expect($tender->linkedToDocuments($owner))->toBeTrue();
    });

    it('is true for a tender team member', function () {
        $tender = Tender::factory()->create();
        $member = User::factory()->create();
        TenderTeamMember::factory()->create(['tender_id' => $tender->id, 'user_id' => $member->id]);

        expect($tender->linkedToDocuments($member))->toBeTrue();
    });

    it('is false for an unrelated user', function () {
        $tender = Tender::factory()->create();
        $stranger = User::factory()->create();

        expect($tender->linkedToDocuments($stranger))->toBeFalse();
    });
});
