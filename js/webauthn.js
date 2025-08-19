async function registerWebAuthn() {
  try {
    const resp = await fetch('/Gestionale25/webauthn_register.php');
    if (!resp.ok) {
      alert('Impossibile registrare una passkey. Effettua prima l\'accesso.');
      return;
    }
    const options = await resp.json();
    options.challenge = Uint8Array.from(atob(options.challenge), c => c.charCodeAt(0));
    options.user.id = Uint8Array.from(atob(options.user.id), c => c.charCodeAt(0));
    const cred = await navigator.credentials.create({ publicKey: options });
    const attestation = {
      id: cred.id,
      rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))),
      type: cred.type,
      response: {
        clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))),
        attestationObject: btoa(String.fromCharCode(...new Uint8Array(cred.response.attestationObject)))
      }
    };
    await fetch('/Gestionale25/webauthn_register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(attestation)
    });
  } catch (err) {
    console.error('Errore nella registrazione WebAuthn', err);
    alert('Registrazione WebAuthn non riuscita.');
  }
}

async function loginWebAuthn() {
  const resp = await fetch('/Gestionale25/webauthn_login.php');
  if (!resp.ok) {
    console.error('Impossibile ottenere le opzioni di login WebAuthn');
    return;
  }
  const options = await resp.json();
  options.challenge = Uint8Array.from(atob(options.challenge), c => c.charCodeAt(0));
  options.allowCredentials = options.allowCredentials.map(c => {
    c.id = Uint8Array.from(atob(c.id), d => d.charCodeAt(0));
    return c;
  });
  if (!options.allowCredentials.length) {
    if (confirm('Nessuna passkey presente. Vuoi crearne una adesso?')) {
      await registerWebAuthn();
    }
    return;
  }
  const cred = await navigator.credentials.get({ publicKey: options });
  const assertion = {
    id: cred.id,
    rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))),
    type: cred.type,
    response: {
      clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))),
      authenticatorData: btoa(String.fromCharCode(...new Uint8Array(cred.response.authenticatorData))),
      signature: btoa(String.fromCharCode(...new Uint8Array(cred.response.signature))),
      userHandle: cred.response.userHandle ? btoa(String.fromCharCode(...new Uint8Array(cred.response.userHandle))) : null
    }
  };
  const verify = await fetch('/Gestionale25/webauthn_login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(assertion)
  });
  const result = await verify.json();
  if (result.success) {
    window.location.href = '/Gestionale25/index.php';
  } else {
    alert('Autenticazione WebAuthn fallita');
  }
}
