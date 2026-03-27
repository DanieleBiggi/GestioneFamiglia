function base64urlDecode(str) {
  str = str.replace(/-/g, '+').replace(/_/g, '/');
  const pad = str.length % 4;
  if (pad) {
    str += '='.repeat(4 - pad);
  }
  const binary = atob(str);
  return Uint8Array.from(binary, c => c.charCodeAt(0));
}

async function registerWebAuthn() {
  try {
    const resp = await fetch('webauthn_register.php');
    if (!resp.ok) {
      alert('Impossibile registrare una passkey. Effettua prima l\'accesso.');
      return;
    }
    const options = await resp.json();
    options.challenge = base64urlDecode(options.challenge);
    options.user.id = base64urlDecode(options.user.id);
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
    const save = await fetch('webauthn_register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(attestation)
    });
    if (save.ok) {
      alert('Passkey registrata con successo.');
    } else {
      alert('Salvataggio passkey non riuscito.');
    }
  } catch (err) {
    console.error('Errore nella registrazione WebAuthn', err);
    alert('Registrazione WebAuthn non riuscita.');
  }
}

async function loginWebAuthn() {
  const resp = await fetch('webauthn_login.php');
  if (!resp.ok) {
    console.error('Impossibile ottenere le opzioni di login WebAuthn');
    return;
  }
  const options = await resp.json();
  options.challenge = base64urlDecode(options.challenge);
  options.allowCredentials = options.allowCredentials.map(c => {
    c.id = base64urlDecode(c.id);
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
  const verify = await fetch('webauthn_login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(assertion)
  });
  const result = await verify.json();
  if (result.success) {
    window.location.href = 'index.php';
  } else {
    alert('Autenticazione WebAuthn fallita');
  }
}
