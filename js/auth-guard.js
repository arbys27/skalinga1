(function () {
  async function getSession() {
    const response = await fetch('api/me.php', { credentials: 'same-origin' });
    if (!response.ok) return null;
    return response.json();
  }

  function loginRedirect() {
    const target = `${window.location.pathname.split('/').pop()}${window.location.search}`;
    window.location.replace(`login.html?redirect=${encodeURIComponent(target)}`);
  }

  window.AuthGuard = {
    async require(allowedRoles) {
      try {
        const session = await getSession();
        if (!session || !session.authenticated || !allowedRoles.includes(session.role)) {
          loginRedirect();
          return null;
        }
        updateProfileCard(session.user, session.role);
        if (allowedRoles.includes('admin') || allowedRoles.includes('sk_official')) {
          normalizeAdminNavigation();
        }
        return session.user;
      } catch (error) {
        loginRedirect();
        return null;
      }
    }
  };

  function updateProfileCard(user, role) {
    const name = user && user.name ? user.name : user && user.identifier ? user.identifier : 'Admin';
    const roleLabel = role === 'admin' ? 'Admin' : role === 'sk_official' ? 'SK Official' : 'Youth';
    const nameElement = document.getElementById('userFullName');
    const roleElement = document.getElementById('userRole');
    const avatarElement = document.getElementById('userAvatar') || document.getElementById('userAvatarInitials');

    if (nameElement) nameElement.textContent = name;
    if (roleElement) roleElement.textContent = roleLabel;
    if (avatarElement) {
      avatarElement.textContent = name.split(/\s+/).filter(Boolean).map(part => part[0]).join('').slice(0, 2).toUpperCase();
    }
  }

  function normalizeAdminNavigation() {
    const managementGroup = Array.from(document.querySelectorAll('.nav-group')).find(group => group.querySelector('.nav-group-title')?.textContent.trim() === 'Management');
    if (!managementGroup) return;

    let masterlistLink = managementGroup.querySelector('a[href="admin-masterlist.html"]');
    if (!masterlistLink) {
      masterlistLink = document.createElement('a');
      masterlistLink.href = 'admin-masterlist.html';
      masterlistLink.className = 'nav-item';
      const youthRegistryLink = managementGroup.querySelector('a[href="youth-registry.html"]');
      if (youthRegistryLink) youthRegistryLink.insertAdjacentElement('afterend', masterlistLink);
      else managementGroup.appendChild(masterlistLink);
    }

    masterlistLink.innerHTML = '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"></path><path d="M4 5.5v16"></path><path d="M8 7h8M8 11h8M8 15h5"></path></svg><span>KK Masterlist</span>';
    masterlistLink.classList.toggle('active', window.location.pathname.endsWith('/admin-masterlist.html'));
  }

  const script = document.currentScript;
  const roles = script && script.dataset.roles ? script.dataset.roles.split(',').map(role => role.trim()) : [];
  if (roles.length) window.AuthGuard.require(roles);
})();
