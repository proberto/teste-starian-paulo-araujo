export interface Task {
  id: number;
  title: string;
  completed: boolean;
  created_at: string;
}

export interface CreateTaskRequest {
  title: string;
}
