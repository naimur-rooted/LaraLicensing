<!-- LOVABLE:BEGIN -->
> [!IMPORTANT]
> This project is connected to [Lovable](https://lovable.dev). Avoid rewriting
> published git history - force pushing, or rebasing/amending/squashing commits
> that are already pushed - as it rewrites history on Lovable's side and the
> user will likely lose their project history.
>
> Commits you push to the connected branch sync back to Lovable and show up in
> the editor, so keep the branch in a working state.
<!-- LOVABLE:END -->

## GitMap Autonomous Pair-Programming Invariants

All development, repository operations, searches, file inspections, script executions, and commits in this workspace MUST strictly use the GitMap CLI (`gitmap`):

1. **Code & Text Search:** Always use `gitmap aum search "<pattern>" [dir] [--ext <ext>]` or `gitmap search "<query>"`. Raw `rg`, `ripgrep`, `grep`, `git grep`, `Select-String`, and `findstr` are banned.
2. **File Discovery:** Always use `gitmap find "<pattern>"`, `gitmap find-files <name>` (`gitmap ff`), or `gitmap list-files` (`gitmap lf`). Raw `Get-ChildItem -Recurse` or `find .` are banned.
3. **Zero-Disk File Inspection:** Use `gitmap cat <filepath>` to stream file contents without disk traversal or buffer bloat.
4. **Script Execution & Runners:**
   - Python: Use `gitmap py "<code-or-script>"` or `gitmap py -c "<expr>"` (uses cached interpreter from `installation.db`).
   - PowerShell: Use `gitmap pwsh "<cmd>"` or `gitmap ps -c "<cmd>"` (with `-NoProfile`).
   - Bash: Use `gitmap bash "<cmd>"` or `gitmap bash -c "<cmd>"`.
5. **Commits & Pushes:** Use dedicated semantic GitMap verbs with hyphen-separated message (`MODULE - summary`):
   - Features: `gitmap cpf "<module> - <summary>"`
   - Bugfixes: `gitmap cbf "<module> - <summary>"` or `gitmap cpb`
   - Chores & Maintenance: `gitmap cpc "<module> - <summary>"`
   - Releases: `gitmap cpr "<module> - <summary>"`
   - Never use raw `git commit` or `git push`. Never put colons or square brackets inside the message argument.
6. **Process & Port Management:** Use `gitmap kill <pid|name|:port>` (e.g. `gitmap kill :8000`).
7. **CI/CD Diagnostics:** Use `gitmap pe -t` and `gitmap pipeline-ai status -t <eta>`.
8. **Multi-Agent Task Tracking:** Use `gitmap task <init|add|claim|complete|fail|status>`.

