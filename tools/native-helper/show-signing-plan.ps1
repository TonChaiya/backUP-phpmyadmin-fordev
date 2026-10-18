param()
[ordered]@{
    mode = 'plan_only'
    action_performed = $false
    digest = 'SHA256'
    timestamp_protocol = 'RFC3161'
    timestamp_digest = 'SHA256'
    required_eku_oid = '1.3.6.1.5.5.7.3.3'
    private_key = 'non_exportable_hardware_or_managed_service'
    signer = 'organization_managed_or_internal_ca'
    self_signed_production_allowed = $false
} | ConvertTo-Json -Compress
