@extends('template.index')

@section('main')
<main class="container p-2">
    <h1>Users</h1>
    <a href="register" class="btn btn-primary">Enregistrer un nouvel utilisateur</a>
    <table class="table">
        <thead>
          <tr>
            <th scope="col">#</th>
            <th scope="col">Name</th>
            <th scope="col">Email</th>
            <th scope="col">Action</th>
            <th scope="col"></th>
          </tr>
        </thead>
        <tbody>
            @if ($users)
                @foreach ($users as $user)
                    <tr>
                        <th scope="row">{{$user->id}}</th>
                        <td>{{$user->name}}</td>
                        <td>{{$user->email}}</td>
                        <td>
                            <a href="/user/{{$user->id}}" class="btn btn-primary">Modifier</a>
                        </td>
                        <td>
                            <form action="{{ route('users.delete', ["id" => $user->id]) }}" method="post">
                                @csrf
                                @method('DELETE')
                                
                                <button type="submit" class="btn btn-primary">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
      </table>
</main>
@endsection