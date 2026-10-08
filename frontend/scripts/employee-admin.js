const page = document.querySelector('.employee-admin-main');

if (page) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const canAssignAdministrativeRoles = page.dataset.assignAdministrativeRoles === 'true';
  const roleLabels = {
    employee: 'Funcionário',
    leader: 'Líder',
    supervisor: 'Supervisor',
    admin: 'Administrador',
    superadmin: 'Superadministrador',
  };
  const form = document.querySelector('#employee-create-form');
  const formStatus = document.querySelector('#employee-form-status');
  const listStatus = document.querySelector('#employee-list-status');
  const tableWrap = document.querySelector('#employee-table-wrap');
  const tableBody = document.querySelector('#employee-table-body');

  function setStatus(element, message, state = '') {
    element.textContent = message;
    element.classList.remove('is-error', 'is-success');
    if (state) element.classList.add(`is-${state}`);
  }

  async function api(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.method === 'POST' ? { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken } : {}),
        ...options.headers,
      },
    });
    const responseText = await response.text();
    let payload = {};
    try {
      payload = JSON.parse(responseText);
    } catch {
      payload = { message: `Resposta inválida do servidor (HTTP ${response.status}). Confira o log do Apache.` };
    }
    if (response.status === 401) {
      window.location.assign('index.html');
      throw new Error('Sua sessão expirou. Entre novamente.');
    }
    if (!response.ok) throw new Error(payload.message ?? 'Não foi possível concluir a operação.');
    return payload;
  }

  function createCell(value, className = '') {
    const cell = document.createElement('td');
    if (className) cell.className = className;
    cell.textContent = value;
    return cell;
  }

  function createRoleSelect(currentRole) {
    const select = document.createElement('select');
    select.className = 'employee-inline-select';
    select.setAttribute('aria-label', 'Nível de acesso');
    const roles = canAssignAdministrativeRoles ? Object.keys(roleLabels) : ['employee', 'leader', 'supervisor'];
    for (const role of roles) {
      const option = document.createElement('option');
      option.value = role;
      option.textContent = roleLabels[role];
      option.selected = role === currentRole;
      select.append(option);
    }
    return select;
  }

  function createActionButton(label, className, callback) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.textContent = label;
    button.addEventListener('click', callback);
    return button;
  }

  function renderRows(employees) {
    tableBody.replaceChildren();
    for (const employee of employees) {
      const row = document.createElement('tr');
      row.append(createCell(employee.email, 'employee-email-cell'));

      const roleCell = document.createElement('td');
      if (employee.canManage) {
        const roleSelect = createRoleSelect(employee.role);
        roleCell.append(roleSelect, createActionButton('Salvar nível', 'employee-row-button', async (event) => {
          event.currentTarget.disabled = true;
          try {
            await api('api.php?action=employee-update', { method: 'POST', body: JSON.stringify({ id: employee.id, role: roleSelect.value }) });
            setStatus(listStatus, 'Nível de acesso atualizado.', 'success');
            await loadEmployees();
          } catch (error) {
            setStatus(listStatus, error.message, 'error');
            event.currentTarget.disabled = false;
          }
        }));
      } else {
        roleCell.textContent = roleLabels[employee.role] ?? employee.role;
      }
      row.append(roleCell);

      const activeBadge = document.createElement('span');
      activeBadge.className = `employee-state ${employee.active ? 'is-active' : 'is-inactive'}`;
      activeBadge.textContent = employee.active ? 'Ativa' : 'Desativada';
      const stateCell = document.createElement('td');
      stateCell.append(activeBadge);
      row.append(stateCell);

      const firebaseCell = document.createElement('td');
      const firebaseBadge = document.createElement('span');
      firebaseBadge.className = `employee-state ${employee.firebase_uid ? 'is-active' : 'is-pending'}`;
      firebaseBadge.textContent = employee.firebase_uid ? 'Vinculada' : 'Conta pendente';
      firebaseCell.append(firebaseBadge);
      row.append(firebaseCell);

      const passwordCell = document.createElement('td');
      const passwordStatus = document.createElement('span');
      passwordStatus.className = 'employee-password-status';
      passwordStatus.textContent = 'Senha nunca exibida';
      passwordCell.append(passwordStatus);
      if (employee.canManage) {
        const setPasswordLabel = employee.firebase_uid ? 'Definir nova senha' : 'Criar conta e definir senha';
        passwordCell.append(createActionButton(setPasswordLabel, 'employee-row-button', (event) => {
          const existing = passwordCell.querySelector('.employee-password-form');
          if (existing) {
            existing.remove();
            event.currentTarget.textContent = setPasswordLabel;
            return;
          }
          const passwordForm = document.createElement('form');
          passwordForm.className = 'employee-password-form';
          passwordForm.innerHTML = '<label>Nova senha temporária<input name="password" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required></label><label>Repetir nova senha<input name="confirm" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required></label><label class="employee-password-show"><input name="show" type="checkbox"> Mostrar enquanto digito</label><button class="employee-row-button is-primary" type="submit">Atribuir senha</button><small>A senha atual não pode ser consultada. Entregue a senha temporária ao integrante por um canal seguro.</small>';
          const inputs = passwordForm.querySelectorAll('input[type="password"]');
          passwordForm.querySelector('[name="show"]').addEventListener('change', (toggleEvent) => {
            inputs.forEach((input) => { input.type = toggleEvent.currentTarget.checked ? 'text' : 'password'; });
          });
          passwordForm.addEventListener('submit', async (submitEvent) => {
            submitEvent.preventDefault();
            const submit = passwordForm.querySelector('button[type="submit"]');
            const values = new FormData(passwordForm);
            const password = String(values.get('password') ?? '');
            const confirm = String(values.get('confirm') ?? '');
            if (password.length < 12) return setStatus(listStatus, 'A nova senha precisa ter pelo menos 12 caracteres.', 'error');
            if (password !== confirm) return setStatus(listStatus, 'As senhas não correspondem.', 'error');
            if (!window.confirm(`Atribuir uma nova senha à conta ${employee.email}? A senha atual será substituída imediatamente.`)) return;
            submit.disabled = true;
            try {
              const result = await api('api.php?action=employee-password-set', { method: 'POST', body: JSON.stringify({ id: employee.id, password }) });
              passwordForm.reset();
              passwordForm.remove();
              const refreshed = await loadEmployees({ showStatus: false });
              const confirmation = refreshed.ok
                ? result.message
                : `${result.message} A lista não foi atualizada; use “Atualizar lista” para conferir os dados mais recentes.`;
              setStatus(listStatus, confirmation, 'success');
            } catch (error) {
              passwordForm.reset();
              setStatus(listStatus, error.message, 'error');
              submit.disabled = false;
            }
          });
          passwordCell.append(passwordForm);
          event.currentTarget.textContent = 'Fechar formulário';
          passwordForm.querySelector('input[name="password"]').focus();
        }));
        passwordCell.append(createActionButton('Enviar link para alterar', 'employee-row-button', async (event) => {
          if (!window.confirm(`Enviar um link de redefinição de senha para ${employee.email}? A senha atual não será mostrada nem alterada até o titular abrir o link recebido.`)) return;
          event.currentTarget.disabled = true;
          try {
            const result = await api('api.php?action=employee-password-reset', {
              method: 'POST',
              body: JSON.stringify({ id: employee.id }),
            });
            setStatus(listStatus, result.message, 'success');
          } catch (error) {
            setStatus(listStatus, error.message, 'error');
            event.currentTarget.disabled = false;
          }
        }));
      } else {
        const restricted = document.createElement('small');
        restricted.textContent = 'Restrito ao nível superior';
        passwordCell.append(restricted);
      }
      row.append(passwordCell);

      const lastLogin = employee.last_login_at ? new Date(`${employee.last_login_at.replace(' ', 'T')}Z`).toLocaleString('pt-BR') : 'Ainda não entrou';
      row.append(createCell(lastLogin));

      const actionCell = document.createElement('td');
      if (employee.canManage) {
        const nextAction = employee.active ? 'employee-deactivate' : 'employee-reactivate';
        const actionLabel = employee.active ? 'Desativar' : 'Reativar';
        actionCell.append(createActionButton(actionLabel, `employee-row-button ${employee.active ? 'is-danger' : 'is-primary'}`, async (event) => {
          const confirmation = employee.active
            ? `Desativar o acesso de ${employee.email}? O registro permanecerá para auditoria.`
            : `Reativar o acesso de ${employee.email}?`;
          if (!window.confirm(confirmation)) return;
          event.currentTarget.disabled = true;
          try {
            await api(`api.php?action=${nextAction}`, { method: 'POST', body: JSON.stringify({ id: employee.id }) });
            setStatus(listStatus, `Acesso de ${employee.email} ${employee.active ? 'desativado' : 'reativado'}.`, 'success');
            await loadEmployees();
          } catch (error) {
            setStatus(listStatus, error.message, 'error');
            event.currentTarget.disabled = false;
          }
        }));
      } else {
        actionCell.textContent = 'Restrito ao nível superior';
      }
      row.append(actionCell);
      tableBody.append(row);
    }
    tableWrap.hidden = employees.length === 0;
    if (employees.length === 0) setStatus(listStatus, 'Nenhum cadastro disponível.');
  }

  async function loadEmployees({ showStatus = true } = {}) {
    if (showStatus) setStatus(listStatus, 'Atualizando cadastros…');
    try {
      const result = await api('api.php?action=employees');
      renderRows(result.employees);
      if (showStatus && result.employees.length > 0) setStatus(listStatus, `${result.employees.length} conta(s) encontrada(s).`);
      return { ok: true };
    } catch (error) {
      if (showStatus) setStatus(listStatus, error.message, 'error');
      return { ok: false, error };
    }
  }

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    setStatus(formStatus, 'Salvando cadastro…');
    try {
      const data = new FormData(form);
      const result = await api('api.php?action=employee-create', {
        method: 'POST',
        body: JSON.stringify({ email: data.get('email'), role: data.get('role') }),
      });
      form.reset();
      setStatus(formStatus, result.message, 'success');
      await loadEmployees();
    } catch (error) {
      setStatus(formStatus, error.message, 'error');
    } finally {
      submit.disabled = false;
    }
  });

  document.querySelector('#employee-refresh')?.addEventListener('click', loadEmployees);
  void loadEmployees();
}
