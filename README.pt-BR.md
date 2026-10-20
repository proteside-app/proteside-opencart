# Proteção de checkout Proteside para OpenCart

[English](README.md) · [Español](README.es.md)

**Download:** o pacote para instalar está anexado à [versão mais recente](https://github.com/proteside-app/proteside-opencart/releases/latest). Este repositório é publicado pela equipe Proteside apenas para download e não aceita contribuições. Dúvidas: [team@proteside.com](mailto:team@proteside.com).

Carrega a proteção client-side do Proteside (PCI DSS 4.0, requisitos 6.4.3 e 11.6.1) nas páginas de pagamento de uma
loja OpenCart usando a sua **chave pública do SDK**. A extensão reproduz a instalação canônica do Proteside: um
bootstrapper inline e síncrono seguido do `shield.js` assíncrono, colocados como **primeiro conteúdo do `<head>`**
para que todos os outros scripts da página sejam observados desde o primeiro byte.

Código da extensão: `proteside` · Versão: 1.0.0 · Bootstrapper embutido: 1.1.3 · Licença: GPL-3.0-or-later

## Requisitos

| | Pacote OpenCart 3.0 | Pacote OpenCart 4 |
| --- | --- | --- |
| OpenCart | 3.0.2.0 a 3.0.5.1 | 4.0.2.0 ou mais novo, incluindo 4.1 |
| Arquivo | `dist/proteside-opencart-3.ocmod.zip` | `dist/proteside.ocmod.zip` (mantenha exatamente este nome) |
| PHP | 7.0 ou mais novo (o que o seu OpenCart usar) | 8.0 ou mais novo |

Os dois pacotes precisam de HTTPS de saída do servidor para `app.proteside.com` (cURL ou `allow_url_fopen`), só para
as verificações de status, e de uma conta Proteside com a chave do SDK (`pk_live_…` ou `pk_test_…`), que fica no
dashboard em **Configurações → Páginas e Domínios**. Ainda não tem conta? Veja <https://app.proteside.com/plans>.

OpenCart 4.0.0.0 e 4.0.1.x não são suportados (usam outro separador de rota e outra API de eventos).

## Instalação

### OpenCart 4.0.2+ / 4.1

1. **Extensões → Instalador → Upload** e escolha `proteside.ocmod.zip`. O nome do arquivo precisa continuar
   `proteside.ocmod.zip`: o OpenCart 4 usa esse nome como pasta da extensão.
2. Na lista de instalados, clique no botão verde **Instalar** de "Proteção de checkout Proteside".
3. **Extensões → Extensões**, escolha o tipo **Módulos**, encontre "Proteção de checkout Proteside" e clique em
   **Instalar** (+) e depois em **Editar** (lápis).
4. Cole a sua chave do SDK e clique em **Salvar**. A página recarrega com o status devolvido pelo Proteside.

### OpenCart 3.0.x

1. **Extensões → Instalador → Upload** e escolha `proteside-opencart-3.ocmod.zip`.
2. **Extensões → Extensões**, escolha **Módulos**, encontre "Proteção de checkout Proteside", clique em **Instalar**
   (+) e depois em **Editar** (lápis).
3. Cole a sua chave do SDK e clique em **Salvar**.

Sem o instalador (nas duas versões), copie os arquivos por FTP: no 3.0, o conteúdo de `upload/` para a raiz da loja;
no 4.x, o conteúdo de `proteside.ocmod.zip` para `extension/proteside/`. Depois siga a partir de **Extensões →
Extensões → Módulos**.

Instalar o módulo registra o evento `proteside` (**Extensões → Eventos**) e, no OpenCart 4, o cron job `proteside`
(**Extensões → Cron Jobs**). Não desative o evento: é ele que insere a proteção.

**Não** é preciso adicionar o módulo a um layout (**Design → Layouts**). Se adicionar, ele não imprime nada.

## Configuração

| Campo | Padrão | O que faz |
| --- | --- | --- |
| Ativar proteção | ligado | Chave geral. |
| Chave do SDK | — | Chave pública `pk_live_`/`pk_test_` + 32 caracteres hexadecimais. Só é salva se o formato for válido. |
| Páginas | checkout, confirmação do pedido | Onde as tags são inseridas (veja abaixo). |
| Padrões de URL personalizados | — | Um por linha, `*` como curinga, comparados com o caminho e a URL da requisição. |
| Canal de release | Seguir o dashboard | `auto` usa o canal informado por `/api/sdk/config`; `stable`/`latest` forçam o canal. |
| Embutir regras de bloqueio (config inline) | ligado | Grava as suas regras ativas e os containers GTM permitidos no bootstrapper (`window.__PROTE_INLINE__`) para o bloqueio começar antes da configuração remota chegar. |
| Calibração (segundos) | vazio | `data-calibration` opcional (10 a 120). Só é usado se o SDK não conseguir carregar a configuração remota. |
| Verificar status a cada | 12 h | 6, 12 ou 24 horas. |
| Reverificar após visitas à loja | ligado | Revalidação adiada (veja "Revalidação"). Desligue se você chamar a URL de cron. |

Botões: **Salvar** grava as configurações e atualiza o status; **Verificar status** atualiza sem salvar.

### Páginas

As páginas são identificadas pela rota do OpenCart (`route`, também definida quando as URLs amigáveis estão ligadas),
nunca pela URL, exceto nos seus padrões personalizados:

| Opção | Rotas |
| --- | --- |
| Checkout (todas as etapas) | `checkout/checkout` e todas as outras rotas `checkout/…` exceto carrinho, sucesso e falha; páginas das extensões de pagamento (`extension/payment/…` no 3.0, `extension/<fornecedor>/payment/…` no 4.x); extensões de checkout em uma página cuja rota contém `checkout` (por exemplo `checkout/simplecheckout`, `extension/quickcheckout/checkout`, `journal3/checkout`) |
| Confirmação do pedido (obrigado) | `checkout/success`, `checkout/failure` (o SDK usa a página de sucesso para confirmar pagamentos com cartão) |
| Carrinho | `checkout/cart` |
| Loja e páginas de produto | `common/home`, `product/…` |
| Minha conta | `account/…` (exceto as páginas de login), `affiliate/…` |
| Página de login | `account/login`, `account/forgotten`, `account/reset` |
| Loja inteira | todas as páginas |
| Padrões de URL personalizados | os seus padrões, ex.: `/finalizar-compra` ou `*route=extension/payment/meupagamento*` |

As etapas do checkout carregadas por AJAX dentro da página de checkout ficam cobertas pelas tags dessa página.

### Multiloja

Com mais de uma loja, aparece um seletor **Configurações de** no topo. Cada loja pode ter chave, páginas e opções
próprias; uma loja sem configurações próprias usa as da loja padrão. O status é verificado e guardado por loja,
usando a URL da loja.

### Card de status

Mostra **Ativo**, **Período de carência**, **Suspenso**, **Inativo**, **Chave inválida**, **Erro de conexão** ou
**Ainda não verificado**, o plano, quando foi a última verificação, avisos (cobrança em carência, chave inválida,
assinatura não ativa, erro de conexão, chave cadastrada em outro domínio) e a decisão: "ativa — a proteção carrega
nas páginas selecionadas" ou "inativa — a proteção NÃO carrega".

Regras de decisão:

| Última resposta definitiva | API agora | Insere a proteção? |
| --- | --- | --- |
| ativo / carência | OK | sim (carência mostra aviso de cobrança) |
| suspenso / inativo / inválido | OK | não |
| nunca validou | indisponível | não (fail-closed) |
| ativo / carência, último sucesso ≤ 48 h | indisponível | sim (tolerância) |
| ativo / carência, último sucesso > 48 h | indisponível | não |

Só HTTP 200 e 401 mudam a decisão; 429, 5xx e timeouts mantêm a anterior e mostram "Erro de conexão". Uma chave
cadastrada em outro domínio é só um aviso; a proteção continua carregando.

## Revalidação

A extensão nunca chama o Proteside enquanto monta uma página. O status é atualizado:

1. **Ao salvar** ou ao clicar em **Verificar status**.
2. **Após visitas à loja (padrão).** Quando o intervalo termina, a próxima página da loja agenda uma verificação que
   roda depois que a página foi entregue. Com PHP-FPM ou LiteSpeed a conexão com o visitante é fechada antes da
   verificação começar; com outras SAPIs (ex.: Apache mod_php) a página já foi enviada por inteiro, mas a conexão
   pode ficar aberta por até 10 s enquanto o Proteside responde. Uma trava impede verificações em paralelo.
3. **URL de cron (opcional).** Aparece em **Diagnóstico** com botão de copiar:

   ```
   https://sua-loja.example/index.php?route=extension/proteside/module/proteside.cron&token=<token>   (OpenCart 4)
   https://sua-loja.example/index.php?route=extension/module/proteside/cron&token=<token>             (OpenCart 3.0)
   ```

   Chame pelo agendador do servidor, por exemplo a cada hora: `0 * * * * curl -fsS '<url>' >/dev/null`. Ela verifica
   todas as lojas cujo intervalo terminou e devolve JSON; acrescente `&force=1` para verificar todas agora. Mantenha
   o token privado; ele é gerado na instalação.
4. **Cron job do OpenCart 4.** O módulo registra o job de hora em hora `proteside` em **Extensões → Cron Jobs**. Ele
   roda quando o servidor chama o cron do OpenCart (`index.php?route=cron/cron` ou `php cron.php`) e quando você
   clica em **Executar** nessa página.

## Ordem de instalação (por que a posição importa)

O bootstrapper registra quantos elementos `<script>` vêm antes dele. Se algum script rodar antes, o SDK informa
`INSTALLATION_ORDER_WARNING` e `Proteside.getStatus().installationOrderValid` fica `false`; a proteção continua, com
cobertura parcial.

A extensão escuta o evento que renderiza o template do header (`catalog/view/common/header/after`) com ordem 9999,
então roda depois das outras extensões que alteram o header, e insere as duas tags logo após a tag `<head>` de
abertura (depois de um `<meta charset>` que venha logo em seguida). Nos temas padrão do OpenCart 3.0 e 4 isso fica
antes do jQuery, do Bootstrap e de qualquer outro script.

**Como verificar:**

- No admin, **Diagnóstico → Testar a instalação** renderiza o header da loja selecionada e informa se o snippet é o
  primeiro script depois do `<head>`.
- Na loja, abra a página de confirmação do pedido ou o checkout, abra o console do navegador e rode
  `Proteside.getStatus()`. `installationOrderValid` precisa ser `true`.
- No código-fonte da página, `<!-- Proteside Start -->` tem que vir logo depois do `<head>` (e do seu
  `<meta charset>`), depois o script inline, depois `<script async src="https://cdn.proteside.com/v1/shield.js" …>`.

Nunca instale o Proteside pelo Google Tag Manager. Se a loja usa GTM, o Proteside precisa rodar antes dele, e esta
extensão garante isso.

## Content Security Policy (CSP)

O OpenCart não envia CSP por padrão. Se a sua loja (ou o proxy ou CDN dela) envia, libere:

- `script-src`: o bootstrapper inline (hash, nonce ou `'unsafe-inline'`) **e** `https://cdn.proteside.com`
- `connect-src`: `https://app.proteside.com` e `https://cdn.proteside.com` (mais `'self'`); o `shield.js` calcula o hash dos scripts externos, inclusive o dele, com um fetch CORS

Hash do bootstrapper 1.1.3 embutido **sem** regras inline: `'sha256-t2ypUgwBPt6N82k2gXq5D5ZHIdxOXxhj6ZISJatVbg8='`. Com
regras inline o script muda; o hash atual aparece sempre em **Diagnóstico → Hash CSP do script inline** e muda
quando as suas regras ou containers GTM mudam no dashboard. Se manter o hash atualizado não for prático, desligue
"Embutir regras de bloqueio" para ficar com o hash publicado.

## Serviços externos / dados enviados

A extensão fala só com o Proteside, só a partir do servidor e só para verificar o status (salvar, "Verificar
status", revalidação adiada, cron). Ela nunca chama o Proteside enquanto monta uma página.

| Requisição | Quando | Dados enviados |
| --- | --- | --- |
| `GET https://app.proteside.com/api/sdk/status` | verificações de status | chave do SDK, URL da loja (`site`), `platform=opencart`, versão da extensão; `User-Agent: Proteside-OpenCart/1.0.0; <URL da loja>` |
| `GET https://app.proteside.com/api/sdk/config` | depois de uma verificação positiva | chave do SDK |

No navegador do visitante, o `shield.js` (carregado de `https://cdn.proteside.com`) envia a telemetria do SDK
(scripts observados, scripts bloqueados, eventos da página) para `https://app.proteside.com/api/sdk/events`; quem faz
isso é o SDK, não a extensão. A chave do SDK é pública por design. A extensão não guarda segredo nenhum além do token
da sua URL de cron.

Termos: <https://proteside.com/termos> · Privacidade: <https://proteside.com/privacidade> · Documentação: <https://docs.proteside.com>

## Desinstalação

**Extensões → Extensões → Módulos → Desinstalar** (–) remove o evento, o cron job (OpenCart 4) e todas as
configurações e status guardados pela extensão; a loja para de carregar a proteção na hora (exceto páginas em cache).
Para remover também os arquivos, desinstale o pacote em **Extensões → Instalador**.

## Limitações

- As tags são inseridas na saída do template do header. Um tema cujo template `common/header` não tenha a tag
  `<head>` de abertura não pode ser coberto; **Testar a instalação** detecta isso.
- Outra extensão que imprima scripts antes do template do header, ou que reescreva a página inteira depois do
  OpenCart (algumas extensões de cache ou minificação), pode colocar scripts antes do Proteside. **Testar a
  instalação** e `Proteside.getStatus()` mostram isso.
- Caches de página inteira servem o HTML que estava em cache; uma mudança de status aparece quando o cache expira.
- Checkouts hospedados fora do OpenCart (páginas de pagamento no domínio do provedor, iframes) não são cobertos. Em
  checkouts de página única que trocam a URL sem recarregar, chame `Proteside.pageChanged(path)` a cada troca de rota.
- A extensão não renderiza selo. O selo é um `<a>` estático copiado do dashboard (Configurações → Loja).
- O bootstrapper vem com a extensão e é atualizado a cada versão.
- No OpenCart 4.1.0.4 o executor de linha de comando `php cron.php` falha antes de chegar a qualquer extensão (ele não
  carrega o Twig); use `index.php?route=cron/cron`, o botão **Executar** ou a URL de cron desta extensão.

## Perguntas frequentes

**O status diz "Ainda não verificado" depois de salvar.** A chave não pôde ser verificada (rede). Clique em
**Verificar status**. A loja não carrega a proteção até receber uma resposta positiva (fail-closed).

**O dashboard mostra aviso de ordem de instalação.** Rode **Diagnóstico → Testar a instalação**. Se outro script vier
antes, procure uma extensão que imprima scripts antes do header ou reescreva a página, e confira se o evento
`proteside` está ativo em **Extensões → Eventos**.

**A chave precisa ser secreta?** Não. Chaves `pk_` são públicas e aparecem no HTML de toda página protegida.

**Funciona com URLs amigáveis (SEO)?** Sim. As páginas são identificadas pela rota, que o OpenCart define a partir da
palavra-chave SEO.

**Quais versões foram testadas?** OpenCart 3.0.5.0, 4.0.2.3 e 4.1.0.4 no PHP 8.5 com MariaDB; veja `VERIFICATION.md`.

## Suporte

<team@proteside.com> · <https://docs.proteside.com> · Dashboard: <https://app.proteside.com>

## Desenvolvimento

```sh
php tests/run.php                 # testes unitários (PHP puro, sem OpenCart)
php tools/build.php               # sincroniza lib/, bootstrapper/ e lang/ nos dois pacotes e gera dist/*.ocmod.zip
php tools/build.php --check       # falha se alguma cópia dos pacotes estiver desatualizada
php tools/php70-compat-check.php  # o pacote do OpenCart 3.0 precisa ser válido no PHP 7.0
```

`lib/`, `bootstrapper/` e `lang/` são as fontes; as cópias dentro de `opencart-3/` e `opencart-4/` são geradas.
`tools/mock-api.php` e `tools/inspect-head.php` apoiam testes em homologação (veja `VERIFICATION.md`).

## Licença

GNU General Public License v3.0 ou posterior — veja `LICENSE`.
