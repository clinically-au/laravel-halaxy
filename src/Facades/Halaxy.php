<?php

declare(strict_types=1);

namespace Clinically\Halaxy\Facades;

use Clinically\Halaxy\Contracts\ClientInterface;
use Clinically\Halaxy\Enums\Region;
use Clinically\Halaxy\HalaxyManager;
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
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Halaxy SDK.
 *
 * Single-tenant usage (via config):
 *   Halaxy::patients()->find($id);
 *   Halaxy::region('eu')->patients()->find($id);
 *
 * Multi-tenant usage (explicit credentials):
 *   Halaxy::for('tenant-123', $clientId, $clientSecret)->patients()->find($id);
 *   Halaxy::withCredentials($clientId, $clientSecret)->patients()->find($id);
 *
 * @method static \Clinically\Halaxy\Halaxy getDefaultInstance()
 * @method static \Clinically\Halaxy\Halaxy for(string $tenantId, string $clientId, string $clientSecret, ?string $region = null)
 * @method static \Clinically\Halaxy\Halaxy withCredentials(string $clientId, string $clientSecret, ?string $region = null)
 * @method static void forgetTenant(string $tenantId)
 * @method static void flush()
 * @method static \Clinically\Halaxy\Halaxy region(string $region)
 * @method static Region getRegion()
 * @method static ClientInterface getClient()
 * @method static PatientResource patients()
 * @method static PractitionerResource practitioners()
 * @method static PractitionerRoleResource practitionerRoles()
 * @method static OrganizationResource organizations()
 * @method static AppointmentResource appointments()
 * @method static ScheduleResource schedules()
 * @method static SlotResource slots()
 * @method static HealthcareServiceResource healthcareServices()
 * @method static ChargeItemDefinitionResource chargeItemDefinitions()
 * @method static CoverageResource coverages()
 * @method static InvoiceResource invoices()
 * @method static InvoiceLineResource invoiceLines()
 * @method static PaymentTransactionResource paymentTransactions()
 * @method static ReferralResource referrals()
 * @method static ReferralDefinitionResource referralDefinitions()
 * @method static DocumentReferenceResource documentReferences()
 * @method static CapabilityStatementResource capabilities()
 * @method static SearchParameterResource searchParameters()
 *
 * @see HalaxyManager
 */
final class Halaxy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HalaxyManager::class;
    }
}
