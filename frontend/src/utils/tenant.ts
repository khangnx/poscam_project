/**
 * Get tenant_id from subdomain (e.g., 'shop-a.localhost' -> 'shop-a')
 * Fallback to localStorage if the host doesn't have a clear subdomain structure.
 */
export function getTenantId(): string | null {
  // Check local storage first if stored from a prior manual login
  const storedTenant = localStorage.getItem('tenant_id')
  if (storedTenant) {
    return storedTenant
  }

  // Attempt to extract from hostname
  const hostname = window.location.hostname
  const parts = hostname.split('.')
  
  // If there are at least 2 parts (e.g. shop-a.localhost) and not starting with www
  if (parts.length >= 2 && parts[0] !== 'www') {
    return parts[0]
  }
  
  return null
}
