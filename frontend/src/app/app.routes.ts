import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    loadChildren: () =>
      import('./features/tasks/tasks.routes').then((m) => m.tasksRoutes),
  },
  {
    path: '**',
    redirectTo: '',
  },
];
