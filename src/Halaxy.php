<?php

declare(strict_types=1);

namespace Clinically\Halaxy;

use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\Resources\Clinical\DocumentReferenceResource;
use Clinically\Halaxy\Resources\Financial\ChargeItemDefinitionResource;
use Clinically\Halaxy\Resources\Financial\CoverageResource;
use Clinically\Halaxy\Resources\Financial\InvoiceLineResource;
use Clinically\Halaxy\Resources\Financial\InvoiceResource;
use Clinically\Halaxy\Resources\Financial\PaymentTransactionResource;
use Clinically\Halaxy\Resources\Financial\ReferralDefinitionResource;
use Clinically\Halaxy\Resources\Financial\ReferralResource;
use Clinically\Halaxy\Resources\Foundations\CapabilityStatementResource;
use Clinically\Halaxy\Resources\Foundations\SearchParameterResource;
use Clinically\Halaxy\Resources\People\OrganizationResource;
use Clinically\Halaxy\Resources\People\PatientResource;
use Clinically\Halaxy\Resources\People\PractitionerResource;
use Clinically\Halaxy\Resources\People\PractitionerRoleResource;
use Clinically\Halaxy\Resources\Scheduling\AppointmentResource;
use Clinically\Halaxy\Resources\Scheduling\HealthcareServiceResource;
use Clinically\Halaxy\Resources\Scheduling\ScheduleResource;
use Clinically\Halaxy\Resources\Scheduling\SlotResource;

final class Halaxy
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly Region $region,
    ) {}

    /**
     * Create a new Halaxy instance for a specific region.
     */
    public function region(string $region): self
    {
        $newRegion = Region::fromString($region);

        return new self(
            client: $this->client->forRegion($region),
            region: $newRegion,
        );
    }

    /**
     * Get the current region.
     */
    public function getRegion(): Region
    {
        return $this->region;
    }

    /**
     * Get the HTTP client.
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    // =========================================================================
    // People Resources
    // =========================================================================

    /**
     * Access Patient resources.
     */
    public function patients(): PatientResource
    {
        return new PatientResource($this->client);
    }

    /**
     * Access Practitioner resources.
     */
    public function practitioners(): PractitionerResource
    {
        return new PractitionerResource($this->client);
    }

    /**
     * Access PractitionerRole resources.
     */
    public function practitionerRoles(): PractitionerRoleResource
    {
        return new PractitionerRoleResource($this->client);
    }

    /**
     * Access Organization resources.
     */
    public function organizations(): OrganizationResource
    {
        return new OrganizationResource($this->client);
    }

    // =========================================================================
    // Scheduling Resources
    // =========================================================================

    /**
     * Access Appointment resources.
     */
    public function appointments(): AppointmentResource
    {
        return new AppointmentResource($this->client);
    }

    /**
     * Access Schedule resources.
     */
    public function schedules(): ScheduleResource
    {
        return new ScheduleResource($this->client);
    }

    /**
     * Access Slot resources.
     */
    public function slots(): SlotResource
    {
        return new SlotResource($this->client);
    }

    /**
     * Access HealthcareService resources.
     */
    public function healthcareServices(): HealthcareServiceResource
    {
        return new HealthcareServiceResource($this->client);
    }

    // =========================================================================
    // Financial Resources
    // =========================================================================

    /**
     * Access ChargeItemDefinition resources.
     */
    public function chargeItemDefinitions(): ChargeItemDefinitionResource
    {
        return new ChargeItemDefinitionResource($this->client);
    }

    /**
     * Access Coverage resources.
     */
    public function coverages(): CoverageResource
    {
        return new CoverageResource($this->client);
    }

    /**
     * Access Invoice resources.
     */
    public function invoices(): InvoiceResource
    {
        return new InvoiceResource($this->client);
    }

    /**
     * Access InvoiceLine resources.
     */
    public function invoiceLines(): InvoiceLineResource
    {
        return new InvoiceLineResource($this->client);
    }

    /**
     * Access PaymentTransaction resources.
     */
    public function paymentTransactions(): PaymentTransactionResource
    {
        return new PaymentTransactionResource($this->client);
    }

    /**
     * Access Referral resources.
     */
    public function referrals(): ReferralResource
    {
        return new ReferralResource($this->client);
    }

    /**
     * Access ReferralDefinition resources.
     */
    public function referralDefinitions(): ReferralDefinitionResource
    {
        return new ReferralDefinitionResource($this->client);
    }

    // =========================================================================
    // Clinical Resources
    // =========================================================================

    /**
     * Access DocumentReference resources.
     */
    public function documentReferences(): DocumentReferenceResource
    {
        return new DocumentReferenceResource($this->client);
    }

    // =========================================================================
    // Foundation Resources
    // =========================================================================

    /**
     * Access CapabilityStatement (API metadata).
     */
    public function capabilities(): CapabilityStatementResource
    {
        return new CapabilityStatementResource($this->client);
    }

    /**
     * Access SearchParameter resources.
     */
    public function searchParameters(): SearchParameterResource
    {
        return new SearchParameterResource($this->client);
    }
}
