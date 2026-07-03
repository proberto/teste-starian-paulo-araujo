import { Component, EventEmitter, Output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-task-form',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './task-form.component.html',
  styleUrl: './task-form.component.scss',
})
export class TaskFormComponent {
  title = '';

  @Output() taskSubmit = new EventEmitter<string>();

  onSubmit(): void {
    const trimmed = this.title.trim();
    if (!trimmed) {
      return;
    }

    this.taskSubmit.emit(trimmed);
    this.title = '';
  }
}
