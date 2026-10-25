import { createFileRoute, redirect } from "@tanstack/react-router";

export const Route = createFileRoute("/auth/sign-in")({
  beforeLoad: ({ search }) => {
    throw redirect({
      to: "/login",
      search,
    });
  },
  component: () => null,
});
