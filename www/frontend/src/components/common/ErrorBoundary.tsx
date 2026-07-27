import { Component, type ErrorInfo, type ReactNode } from 'react';

type ErrorBoundaryProps = {
  children: ReactNode;
};

type ErrorBoundaryState = {
  error: Error | null;
};

export default class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  state: ErrorBoundaryState = { error: null };

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('[ErrorBoundary] render crash', error, info.componentStack);
  }

  private handleRetry = (): void => {
    this.setState({ error: null });
    window.location.reload();
  };

  render(): ReactNode {
    if (this.state.error) {
      return (
        <div className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-slate-50 px-4 text-center">
          <p className="text-base font-medium text-slate-700">일시적인 오류가 발생했습니다.</p>
          <button
            type="button"
            onClick={this.handleRetry}
            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
          >
            새로고침
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}
