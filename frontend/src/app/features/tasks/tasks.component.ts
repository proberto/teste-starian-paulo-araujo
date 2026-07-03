import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TaskService } from './services/task.service';
import { Task } from '../../shared/models/task.model';
import { TaskFormComponent } from './components/task-form/task-form.component';
import { TaskListComponent } from './components/task-list/task-list.component';

@Component({
  selector: 'app-tasks',
  standalone: true,
  imports: [CommonModule, TaskFormComponent, TaskListComponent],
  templateUrl: './tasks.component.html',
  styleUrl: './tasks.component.scss',
})
export class TasksComponent implements OnInit {
  tasks: Task[] = [];
  loading = false;
  error: string | null = null;

  constructor(private taskService: TaskService) {}

  ngOnInit(): void {
    this.loadTasks();
  }

  loadTasks(): void {
    this.loading = true;
    this.error = null;

    this.taskService.getTasks().subscribe({
      next: (tasks) => {
        this.tasks = tasks;
        this.loading = false;
      },
      error: () => {
        this.error = 'Não foi possível carregar as tarefas. Tente novamente.';
        this.loading = false;
      },
    });
  }

  onTaskSubmit(title: string): void {
    this.error = null;

    this.taskService.createTask({ title }).subscribe({
      next: (task) => {
        this.tasks = [task, ...this.tasks];
      },
      error: () => {
        this.error = 'Não foi possível adicionar a tarefa. Tente novamente.';
      },
    });
  }

  onTaskRemove(id: number): void {
    this.error = null;

    this.taskService.deleteTask(id).subscribe({
      next: () => {
        this.tasks = this.tasks.filter((task) => task.id !== id);
      },
      error: () => {
        this.error = 'Não foi possível remover a tarefa. Tente novamente.';
      },
    });
  }
}
